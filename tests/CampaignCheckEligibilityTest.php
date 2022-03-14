<?php

use App\AccessToken;
use App\Customer;
use App\PurchaseTransaction;
use App\Voucher;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class CampaignCheckEligibilityTest extends TestCase
{
    use DatabaseTransactions;

    protected $endpoint = '/api/check-eligibility';

    public function testShouldAllVouchersRedeemed()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;
        $voucher = factory(Voucher::class, 1000)->states('redeemed')->create();

        $header = ['Authorization' => 'Bearer ' . $accessToken];

        $this->get($this->endpoint, $header)->seeJsonEquals([
            'eligibility' => false,
            'error_message' => 'All vouchers redeemed.'
        ]);
    }

    public function testShouldAllVouchersLocked()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;

        $otherCustomer = factory(Customer::class)->create();
        $voucher = factory(Voucher::class)->states('locked')->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $header = ['Authorization' => 'Bearer ' . $accessToken];

        $this->get($this->endpoint, $header)->seeJsonEquals([
            'eligibility' => false,
            'error_message' => 'All vouchers have been claimed.'
        ]);
    }

    public function testShouldVoucherRedeemed()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;
        $voucher = factory(Voucher::class)->states('redeemed')->create([
            'customer_id' => $customer->id,
        ]);

        factory(Voucher::class, 5)->create();

        $header = ['Authorization' => 'Bearer ' . $accessToken];

        $this->get($this->endpoint, $header)->seeJsonEquals([
            'eligibility' => false,
            'error_message' => 'Redeemed.'
        ]);
    }

    public function testShouldEligibleVoucherLocked()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;
        $voucher = factory(Voucher::class)->states('locked')->create([
            'customer_id' => $customer->id,
        ]);

        factory(Voucher::class, 5)->create();

        $header = ['Authorization' => 'Bearer ' . $accessToken];

        $this->get($this->endpoint, $header)->seeJsonEquals([
            'eligibility' => true,
            'message' => 'Voucher locked, proceed to validate.'
        ]);
    }

    public function testShouldInsufficientTransactionCount()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;

        factory(Voucher::class, 3)->create();

        $header = ['Authorization' => 'Bearer ' . $accessToken];

        $this->get($this->endpoint, $header)->seeJsonEquals([
            'eligibility' => false,
            'error_message' => 'Less than 3 transactions in 30 days.'
        ]);
    }

    public function testShouldInsufficientTransactionTotal()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;

        factory(Voucher::class, 3)->create();
        factory(PurchaseTransaction::class, 3)->create([
            'customer_id' => $customer->id
        ]);

        $header = ['Authorization' => 'Bearer ' . $accessToken];

        $this->get($this->endpoint, $header)->seeJsonEquals([
            'eligibility' => false,
            'error_message' => 'Total transactions less than $100.'
        ]);
    }

    public function testShouldEligibleSuccess()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;

        factory(Voucher::class, 3)->create();
        factory(PurchaseTransaction::class, 3)->create([
            'customer_id' => $customer->id,
            'total_spent' => 40,
            'transaction_at' => date('Y-m-d 00:00:00', strtotime('-30 days')),
        ]);

        $header = ['Authorization' => 'Bearer ' . $accessToken];

        $this->get($this->endpoint, $header)->seeJsonEquals([
            'eligibility' => true,
            'message' => 'Success.'
        ]);
    }
}
