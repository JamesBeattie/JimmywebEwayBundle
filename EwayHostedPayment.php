<?php

namespace Jimmyweb\EwayBundle;

class EwayHostedPayment
{

  public $success = false;
  public $message = ''; // detailed messages from for internal use/logging
  public $response_msg = ''; // for holding response messages for users to see
  private $live_url = 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp';
  private $test_url = 'https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp';
  private $ewayCustomerID;
  private $ca_info_file;
  private $required_params = array(
    'ewayCustomerID',
    'ewayTotalAmount',
    'ewayCardNumber',
    'ewayCardExpiryMonth',
    'ewayCardExpiryYear',
    'ewayCVN',
    'ewayCardHoldersName',
    'ewayOption1',
    'ewayOption2',
    'ewayOption3'
  );

  public function __construct($ewayCustomerID, $use_test_gateway = false, $ca_info_file = false)
  {
    $this->ewayCustomerID = $ewayCustomerID;
    $this->gateway_url = $use_test_gateway ? $this->test_url : $this->live_url;
    $this->ca_info_file = $ca_info_file ? $ca_info_file : false;
  }

  public function doPayment(array $params)
  {

    // add the customer id to the request
    $params['ewayCustomerID'] = $this->ewayCustomerID;

    // validate supplied params
    $this->validateParams($params);

    // convert the params to xml
    $params = $this->paramsToXML($params);

    // perform the transaction
    $result = $this->transact($params);

    return $this->success;

  }

  private function transact($params)
  {

    //open connection
    $ch = curl_init();

    // if a certificate authority info file has been provided, hook it in
    if($this->ca_info_file)
    {
      curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
      curl_setopt ($ch, CURLOPT_CAINFO, $this->ca_info_file);
    }

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL,$this->gateway_url);
    curl_setopt($ch,CURLOPT_POST,count($params));
    curl_setopt($ch,CURLOPT_POSTFIELDS,$params);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

    //execute post
    $result = curl_exec($ch);

    // throw exception on error
    if($result === false)
    {
      throw new Exception(curl_error($ch));
    }

    //close connection
    curl_close($ch);

    // parse and return results
    return $this->processResult($result);

  }

  private function paramsToXML($params)
  {
      $xml = new \SimpleXMLElement('<ewaygateway></ewaygateway>');
      foreach($params as $key => $val)
      {
          $xml->{$key} = $val;
      }
      return $xml->asXML();
  }

  private function processResult($result)
  {

    // array is an XML string. Parse into SimpleXML
    $xml = new \SimpleXMLElement($result);

    // see if transaction was successful
    if($xml->ewayTrxnStatus == 'True')
    {
      $this->success = true;
    }
    else
    {
      $this->success = false;
    }
    // set messages into public properties
    $this->message = $xml->ewayTrxnError;
    $message_parts = explode(',', $xml->ewayTrxnError);
    $this->response_msg = $message_parts[1];
    return $result;
  }


  private function validateParams($params)
  {
    if(strlen($params['ewayCustomerInvoiceDescription']) > 34)
    {
      throw new Exception("Parameter \"ewayCustomerInvoiceDescription\" must not exceed 34 chars in length.");
    }
    if(strlen($params['ewayCardNumber']) < 15)
    {
      throw new Exception("Parameter \"ewayCardNumber\" must contain at least 15 integers.");
    }
    if(strpos($params['ewayTotalAmount'],'.') !== false)
    {
      throw new Exception("Parameter \"ewayTotalAmount\" must be submitted in cents without a decimal point.");
    }
    foreach($this->required_params as $param)
    {
      if(!isset($params[$param]))
      {
        throw new Exception("Madatory parameter \"$param\" was not supplied.");
      }
    }
  }

}