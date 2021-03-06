<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\User as User;
use DB;

class UserAuthTest extends TestCase
{
    private $baseURL = 'api/user/';
    use RefreshDatabase;

    public function register()
    {
        return $this->post(
            $this->baseURL . 'register',

            [
                'firstname' => 'Test',
                'lastname' => 'Doe',
                'phone' => '2345678',
                'email' => 'test@email.com',
                'password' => 'testpassword',
                'country_id' => 1,
            ]
        );
    }

    /** Test if homepage is accessible
     * @test
     * @return void */
    public function homepage_test()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    /** Test if user can register
     * @test
     * @return void */
    public function new_user_can_register()
    {
        $response = $this->register();
        $this->assertCount(1, User::all());
        $response->assertStatus(201);
    }

    /** Test for duplicate email
     * @test
     * @return void */
    public function email_is_unique()
    {
        $response = $response = $this->register();

        $response = $response = $this->register();

        $response->assertStatus(400);
        $response->assertJSON(['result' => 0]);
        $this->assertCount(1, User::all());
    }

    /** Test if user can sign in
     *  @test
     *  @return void */
    public function user_can_login()
    {
        $this->register();
        $this->assertCount(1, User::all());

        $response = $this->post($this->baseURL . 'login', [
            'email' => 'test@email.com',
            'password' => 'testpassword',
        ]);
        $response->assertJSON(['result' => 1]);
        $response->assertOk();
    }
    /** Test Wrong user cant sign in
     * @test
     *  @return void */
    public function wrong_user_cannot_login()
    {
        $this->register();
        $this->assertCount(1, User::all());

        $response = $this->post($this->baseURL . 'login', [
            'email' => 'wrong@email.com',
            'password' => 'testpassword',
        ]);
        $response->assertJSON(['result' => 0]);
        $response->assertStatus(401);
    }

    /** Test if user can logout
     * @test
     *  @return void */
    public function user_can_logout()
    {
        $this->register();
        $payload = [
            'email' => 'test@email.com',
            'password' => 'testpassword',
        ];
        $response = $this->post($this->baseURL . 'login', $payload);

        $data = $response->decodeResponseJson();
        $token = $data['data']['token'];
        $logout = $this->post(
            $this->baseURL . 'logout',
            [],
            ['HTTP_Authorization' => 'Bearer' . $token]
        );

        $logout->assertJSON(['result' => 1]);
        $logout->assertOk();
    }

    /** Test if user can request new password
     * change
     * @test
     *  @return void */
    public function user_request_password_change()
    {
        $this->register();
        $response = $this->post($this->baseURL . 'forgot/password', [
            'email' => 'test@email.com',
        ]);
        $response->assertJSON(['result' => 1]);
        $passwordResets = DB::table('password_resets')->get();
        $this->assertCount(1, $passwordResets);
    }
    /** Test if user can request new password
     * change
     * @test
     *  @return void */
    public function unregistered_user_cant_request_password_change()
    {
        $this->register();
        $response = $this->post($this->baseURL . 'forgot/password', [
            'email' => 'testt@email.com',
        ]);
        $response->assertJSON(['result' => 0]);
    }

    /** Test if user can reset password to new password
     * change
     * @test
     *  @return void */
    public function user_can_reset_password()
    {
        $this->register();
        $this->post($this->baseURL . 'forgot/password', [
            'email' => 'test@email.com',
        ]);
        $passwordResets = DB::table('password_resets')
            ->where('email', 'test@email.com')
            ->first();

        $response = $this->post(
            $this->baseURL . 'reset/password/' . $passwordResets->token,
            [
                'password' => 'newpassword',
            ]
        );
        $response->assertJSON(['result' => 1]);
    }

    /** Test if invalid token can reset password
     * @test
     *  @return void */
    public function invalid_token_cant_reset_password()
    {
        $response = $this->post(
            $this->baseURL . 'reset/password/invalidtoken',
            [
                'password' => 'newpassword',
            ]
        );
        $response->assertJSON([
            'result' => 0,
            'error' => ['token' => 'Invalid Token'],
        ]);
    }

    /** Test  expire token cannot reset password
     * @test
     *  @return void */
    public function expired_token_cant_reset_password()
    {
        //create expired token here

        $data = [
            'token' => 'expiredtoken',
            'email' => 'test@gmail.com',
            'updated_at' => '2015-08-16 18:04:51',
        ];
        DB::table('password_resets')->insert($data);

        $response = $this->post(
            $this->baseURL . 'reset/password/expiredtoken',
            [
                'password' => 'newpassword',
            ]
        );
        $response->assertJSON([
            'result' => 0,
            'error' => ['token' => 'Token Expired'],
        ]);
    }
}