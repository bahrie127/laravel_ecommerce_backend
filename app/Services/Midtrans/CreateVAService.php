<?php

namespace App\Services\Midtrans;

use Midtrans\CoreApi;

class CreateVAService extends Midtrans
{
    protected $order;

    public function __construct($order)
    {
        parent::__construct();

        $this->order = $order;
    }

    public function getVA()
    {

        $itemDetails = [];
        foreach ($this->order->orderItems as $orderItem) {
            $itemDetails[] = [
                'id' => $orderItem->product_id,
                //price string double to integer
                'price' => intval($orderItem->price),
                'quantity' => $orderItem->quantity,
                'name' => mb_substr($orderItem->product->name, 0, 50),
            ];
        }

        //add shipping cost to item details
        $itemDetails[] = [
            'id' => 'SHIPPING_COST',
            'price' => (int) $this->order->shipping_price,
            'quantity' => 1,
            'name' => 'Ongkos kirim',
        ];

        $params = [
            'payment_type' => 'bank_transfer',
            'transaction_details' => [
                'order_id' => $this->order->transaction_number,
                'gross_amount' => (int) $this->order->grand_total,
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => $this->order->user->name,
                'email' => $this->order->user->email
            ],
            'bank_transfer' => [
                'bank' => $this->order->payment_va_name,
            ],
        ];


        $response = CoreApi::charge($params);

        return $response;
    }
}
