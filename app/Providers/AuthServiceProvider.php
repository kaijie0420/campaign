<?php

namespace App\Providers;

use App\User;
use App\Customer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('api', function ($request) {
            $authHeader = $request->header('Authorization');
            if ($authHeader) {
                $split = explode(' ', $authHeader);

                if (count($split) == 2) {
                    $token = $split[1];

                    if ($split[0] == 'Bearer') {
                        return Customer::whereHas('accessToken', function ($query) use ($token) {
                            $query->where('token', $token);
                        })->first();
                    }
                }
            }
        });
    }
}   
