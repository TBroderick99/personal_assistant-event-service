<?php

namespace Tests\Unit\Services;

use App\Services\UserService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set the config value for testing
        $this->app['config']->set('services.user_service.base_url', 'http://user-service.test');
        
        $this->userService = new UserService();
    }

    protected function tearDown(): void
    {
        Http::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_get_users_by_ids_success()
    {
        $userIds = ['user-1', 'user-2', 'user-3'];
        $expectedUsers = [
            ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 'user-2', 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['id' => 'user-3', 'name' => 'Bob Johnson', 'email' => 'bob@example.com'],
        ];

        Http::fake([
            'user-service.test/api/users*' => Http::response([
                'success' => true,
                'data' => $expectedUsers
            ], 200)
        ]);

        $result = $this->userService->getUsersByIds($userIds);

        // The service returns an indexed array by user ID
        $expectedIndexed = [
            'user-1' => ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'],
            'user-2' => ['id' => 'user-2', 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            'user-3' => ['id' => 'user-3', 'name' => 'Bob Johnson', 'email' => 'bob@example.com'],
        ];

        $this->assertEquals($expectedIndexed, $result);

        Http::assertSent(function (Request $request) use ($userIds) {
            return $request->url() === 'http://user-service.test/api/users?ids=user-1%2Cuser-2%2Cuser-3'
                && $request->method() === 'GET';
        });
    }

    public function test_get_users_by_ids_empty_array()
    {
        $result = $this->userService->getUsersByIds([]);

        $this->assertEquals([], $result);
        Http::assertNothingSent();
    }

    public function test_get_users_by_ids_api_failure()
    {
        $userIds = ['user-1', 'user-2'];

        Http::fake([
            'user-service.test/api/users*' => Http::response([
                'success' => false,
                'message' => 'Internal server error'
            ], 500)
        ]);

        $result = $this->userService->getUsersByIds($userIds);

        $this->assertEquals([], $result);
    }

    public function test_get_users_by_ids_network_failure()
    {
        $userIds = ['user-1', 'user-2'];

        Http::fake([
            'user-service.test/api/users*' => Http::response('', 500)
        ]);

        $result = $this->userService->getUsersByIds($userIds);

        $this->assertEquals([], $result);
    }

    public function test_get_user_by_id_success()
    {
        $userId = 'user-1';
        $expectedUser = ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com'];

        Http::fake([
            'user-service.test/api/users/user-1' => Http::response([
                'success' => true,
                'data' => $expectedUser
            ], 200)
        ]);

        $result = $this->userService->getUserById($userId);

        $this->assertEquals($expectedUser, $result);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://user-service.test/api/users/user-1'
                && $request->method() === 'GET';
        });
    }

    public function test_get_user_by_id_not_found()
    {
        $userId = 'non-existent-user';

        Http::fake([
            'user-service.test/api/users/non-existent-user' => Http::response([
                'success' => false,
                'message' => 'User not found'
            ], 404)
        ]);

        $result = $this->userService->getUserById($userId);

        $this->assertNull($result);
    }

    public function test_get_user_by_id_api_failure()
    {
        $userId = 'user-1';

        Http::fake([
            'user-service.test/api/users/user-1' => Http::response([
                'success' => false,
                'message' => 'Internal server error'
            ], 500)
        ]);

        $result = $this->userService->getUserById($userId);

        $this->assertNull($result);
    }

    public function test_user_exists_true()
    {
        $userId = 'user-1';

        Http::fake([
            'user-service.test/api/users/user-1' => Http::response([
                'success' => true,
                'data' => ['id' => 'user-1', 'name' => 'John Doe', 'email' => 'john@example.com']
            ], 200)
        ]);

        $result = $this->userService->userExists($userId);

        $this->assertTrue($result);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://user-service.test/api/users/user-1'
                && $request->method() === 'GET';
        });
    }

    public function test_user_exists_false()
    {
        $userId = 'non-existent-user';

        Http::fake([
            'user-service.test/api/users/non-existent-user' => Http::response([
                'success' => false,
                'message' => 'User not found'
            ], 404)
        ]);

        $result = $this->userService->userExists($userId);

        $this->assertFalse($result);
    }

    public function test_user_exists_api_failure()
    {
        $userId = 'user-1';

        Http::fake([
            'user-service.test/api/users/user-1' => Http::response([
                'success' => false,
                'message' => 'Internal server error'
            ], 500)
        ]);

        $result = $this->userService->userExists($userId);

        $this->assertFalse($result);
    }

    public function test_user_exists_network_failure()
    {
        $userId = 'user-1';

        Http::fake([
            'user-service.test/api/users/user-1' => Http::response('', 500)
        ]);

        $result = $this->userService->userExists($userId);

        $this->assertFalse($result);
    }

    public function test_get_users_by_ids_handles_malformed_response()
    {
        $userIds = ['user-1', 'user-2'];

        Http::fake([
            'user-service.test/api/users*' => Http::response('invalid json', 200)
        ]);

        $result = $this->userService->getUsersByIds($userIds);

        $this->assertEquals([], $result);
    }

    public function test_get_user_by_id_handles_malformed_response()
    {
        $userId = 'user-1';

        Http::fake([
            'user-service.test/api/users/user-1' => Http::response('invalid json', 200)
        ]);

        $result = $this->userService->getUserById($userId);

        $this->assertNull($result);
    }

    public function test_user_exists_handles_malformed_response()
    {
        $userId = 'user-1';

        Http::fake([
            'user-service.test/api/users/user-1' => Http::response('invalid json', 200)
        ]);

        $result = $this->userService->userExists($userId);

        $this->assertFalse($result);
    }
}
