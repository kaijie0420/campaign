<?php

use App\AccessToken;
use App\Customer;
use App\PurchaseTransaction;
use App\Voucher;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(Customer::class, 100)->create()->each(function ($customer) {
            $customer->accessToken()->save(factory(AccessToken::class)->make());
        });
        factory(PurchaseTransaction::class, 300)->create();
        factory(Voucher::class, 1000)->create();
    }
}
