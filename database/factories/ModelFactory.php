<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\AccessToken;
use App\Customer;
use App\PurchaseTransaction;
use App\Voucher;
use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(Customer::class, function (Faker $faker) {
    return [
        'first_name' => $faker->firstName,
        'last_name' => $faker->lastName,
        'gender' => $faker->randomElement(['male', 'female']),
        'date_of_birth' => $faker->date(),
        'contact_number' => $faker->phoneNumber(),
        'email' => $faker->email,
    ];
});

$factory->define(AccessToken::class, function (Faker $faker) {
    return [
        'customer_id' => Customer::inRandomOrder()->first()->id,
        'token' => $faker->regexify('[A-Za-z0-9]{24}'),
    ];
});

$factory->define(PurchaseTransaction::class, function (Faker $faker) {
    return [
        'customer_id' => Customer::inRandomOrder()->first()->id,
        'total_spent' => $faker->randomFloat(2, 1, 100),
        'total_saving' => $faker->randomFloat(2, 1, 100),
        'transaction_at' => $faker->date() . ' ' . $faker->time(),
    ];
});

$factory->define(Voucher::class, function (Faker $faker) {
    return [
        'code' => $faker->regexify('[A-Za-z0-9]{16}'),
    ];
});

$factory->state(Voucher::class, 'redeemed', [
    'redeemed' => true
]);

$factory->state(Voucher::class, 'locked', [
    'locked_at' => date('Y-m-d H:i:s'),
]);