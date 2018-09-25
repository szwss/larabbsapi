<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Topic;

use Tests\Traits\ActingJWTUser;

class TopicApiTest extends TestCase
{

    protected $user;

    /*
    * 修改测试用例，使用该 Trait。
    * */
    use ActingJWTUser;

    /**
     * A basic test example.
     *
     * @return void
     */
//    public function testExample()
//    {
//        $this->assertTrue(true);
//    }

    /*
     * setUp 方法会在测试开始之前执行，我们先创建一个用户，测试会以该用户的身份进行测试。
     * */
    public function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
    }

    /*
     * 测试发布话题
     * 就是一个测试用户，测试发布话题。使用 $this->json 可以方便的模拟各种 HTTP 请求：
     * 第一个参数 —— 请求的方法，发布话题使用的是 POST 方法；
     * 第二个参数 —— 请求地址，请求 /api/topics；
     * 第三个参数 —— 请求参数，传入 category_id，body，title，这三个必填参数；
     * 第四个参数 —— 请求 Header，可以直接设置 header，也可以利用 withHeaders 方法达到同样的目的；
     * */
    public function testStoreTopic()
    {
        $data = ['category_id' => 1, 'body' => 'test body', 'title' => 'test title'];

        /*
         *
         * */
//        $token = \Auth::guard('api')->fromUser($this->user);
//        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
//            ->json('POST', '/api/topics', $data);
        $response = $this->JWTActingAs($this->user)
            ->json('POST', '/api/topics', $data);
        /*
         *
         * */
        $assertData = [
            'category_id' => 1,
            'user_id' => $this->user->id,
            'title' => 'test title',
            'body' => clean('test body', 'user_topic_body'),
        ];

        $response->assertStatus(201)
            ->assertJsonFragment($assertData);
    }

    /*
     * 测试修改话题
     *
     * 我们增加了 testUpdateTopic 测试用例，要修改话题，首先需要为用户创建一个话题，所以增加了 makeTopic，
     * 为当前测试的用户生成一个话题。代码与发布话题类似，
     * 准备好要修改的话题数据 $editData，调用 修改话题 接口，修改刚才创建的话题，
     * 最后断言响应状态码为 200 以及结果中包含 $assertData。
     * 执行测试：phpunit
     * */
    public function testUpdateTopic()
    {
        $topic = $this->makeTopic();

        $editData = ['category_id' => 2, 'body' => 'edit body', 'title' => 'edit title'];

        $response = $this->JWTActingAs($this->user)
            ->json('PATCH', '/api/topics/'.$topic->id, $editData);

        $assertData= [
            'category_id' => 2,
            'user_id' => $this->user->id,
            'title' => 'edit title',
            'body' => clean('edit body', 'user_topic_body'),
        ];

        $response->assertStatus(200)
            ->assertJsonFragment($assertData);
    }

    protected function makeTopic()
    {
        return factory(Topic::class)->create([
            'user_id' => $this->user->id,
            'category_id' => 1,
        ]);
    }



    /*
     * 测试查看话题
     * 增加了两个测试用户 testShowTopic 和 testIndexTopic，分别测试 话题详情 和 话题列表。这两个接口不需要用户登录即可访问，所以不需要传入 Token。
     * testShowTopic 先创建一个话题，然后访问 话题详情 接口，断言响应状态码为 200 以及响应数据与刚才创建的话题数据一致。
     * testIndexTopic 直接访问 话题列表 接口，断言响应状态码为 200，断言响应数据结构中有 data 和 meta。
     * 执行测试：phpunit
     * */
    public function testShowTopic()
    {
        $topic = $this->makeTopic();
        $response = $this->json('GET', '/api/topics/'.$topic->id);

        $assertData= [
            'category_id' => $topic->category_id,
            'user_id' => $topic->user_id,
            'title' => $topic->title,
            'body' => $topic->body,
        ];

        $response->assertStatus(200)
            ->assertJsonFragment($assertData);
    }

    public function testIndexTopic()
    {
        $response = $this->json('GET', '/api/topics');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    /*
     * 测试删除话题
     * 首先通过 makeTopic 创建一个话题，然后通过 DELETE 方法调用 删除话题 接口，将话题删除，断言响应状态码为 204。
     * 接着请求话题详情接口，断言响应状态码为 404，因为该话题已经被删除了，所以会得到 404。
     * 执行测试：phpunit
     * */
    public function testDeleteTopic()
    {
        $topic = $this->makeTopic();
        $response = $this->JWTActingAs($this->user)
            ->json('DELETE', '/api/topics/'.$topic->id);
        $response->assertStatus(204);

        $response = $this->json('GET', '/api/topics/'.$topic->id);
        $response->assertStatus(404);
    }
}
