<?php

namespace Jimmyweb\EwayBundle;

class EwayService {

    public $customer_id;
    public $use_test_gateway;
    private $eway;

    public function __construct($customer_id, $use_test_gateway = false) {
        $this->customer_id = $customer_id;
        $this->use_test_gateway = $use_test_gateway;
    }

    public function doHostedPayment(array $params)
    {
        $this->eway = new EwayHostedPayment($this->customer_id, $this->use_test_gateway);

        $success = $this->eway->doPayment($params);

        return array(
          'payment' => $this->eway,
          'success' => $success,
        );
    }

}