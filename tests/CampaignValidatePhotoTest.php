<?php

use App\AccessToken;
use App\Customer;
use App\Voucher;
use App\Helpers\ImageRecognitionHelper;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class CampaignValidatePhotoTest extends TestCase
{
    use DatabaseTransactions;

    protected $endpoint = '/api/validate-photo';

    private function getPhoto()
    {
        return new \Illuminate\Http\UploadedFile(resource_path('tests/test.png'), 'test.png', null, null, true);
    }

    public function testShouldFailValidation()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;

        $header = ['Authorization' => 'Bearer ' . $accessToken];

        $this->post($this->endpoint, [], $header)->seeJsonEquals([
            'photo' => ["The photo field is required."]
        ]);
    }

    public function testShouldHaveNoVoucherLocked()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;

        $header = ['HTTP_Authorization' => 'Bearer ' . $accessToken];

        $response = $this->call(
            'POST', $this->endpoint, ['photo' => $this->getPhoto()], [], [], $header
        );

        $response->assertStatus(404);
    }

    public function testShouldPassImageRecognition()
    {
        $customer = factory(Customer::class)->create();
        $accessToken = factory(AccessToken::class)->create()->token;
        $voucher = factory(Voucher::class)->states('locked')->create([
            'customer_id' => $customer->id,
        ]);

        $header = ['HTTP_Authorization' => 'Bearer ' . $accessToken];

        // $mock = Mockery::mock(ImageRecognitionHelper::class);
        // $this->app->instance(ImageRecognitionHelper::class, $mock);
        // $mock->shouldReceive('validate')->once()->andReturn(true);
        ImageRecognitionHelper::shouldReceive('validate')->once()->andReturn(true);
        
        // $mock = $this->partialMock(ImageRecognitionHelper::class, function (MockInterface $mock) {
        //     $mock->shouldReceive('validate')
        //         ->andReturnUsing(function() {
        //             return true;
        //         });
        // });

        $response = $this->call(
            'POST', $this->endpoint, ['photo' => $this->getPhoto()], [], [], $header
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'code' => $voucher->code,
                'message' => 'Success.'
            ]);
    }
}