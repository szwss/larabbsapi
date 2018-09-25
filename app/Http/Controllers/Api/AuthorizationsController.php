<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
//use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SocialAuthorizationRequest;
use App\Models\User;
use App\Http\Requests\Api\AuthorizationRequest;

use Auth;





use Zend\Diactoros\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\AuthorizationServer;


use App\Traits\PassportToken;


class AuthorizationsController extends Controller
{
    use PassportToken;
//    public function store(AuthorizationRequest $request)
//    {
//        $username = $request->username;
//
//        filter_var($username, FILTER_VALIDATE_EMAIL) ?
//            $credentials['email'] = $username :
//            $credentials['phone'] = $username;
//
//        $credentials['password'] = $request->password;
//
//        if (!$token = Auth::guard('api')->attempt($credentials)) {
////            return $this->response->errorUnauthorized('用户名或密码错误');
//            return $this->response->errorUnauthorized(trans('auth.failed'));
//
//        }
//
//        return $this->respondWithToken($token)->setStatusCode(201);
//    }

    /*
     * Passport 提供的默认路由为 http://larabbs.test/oauth/token ，
     * 而我们的现在接口统一都有 /api 的前缀，所以我们不使用 Passport 默认的路由，
     * 依然使用 /api/authorizations。先来修改登录接口：
     *
     * 逻辑很简单，我们注入了 AuthorizationServer 和 ServerRequestInterface ，
     * 调用 AuthorizationServer 的 respondToAccessTokenRequest 方法并直接返回。
     *
     * respondToAccessTokenRequest 会依次处理：
     * 检测 client 参数是否正确；
     * 检测 scope 参数是否正确；
     * 通过用户名查找用户；
     * 验证用户密码是否正确；
     * 生成 Response 并返回；
     *
     * 最终返回的 Response 是 Zend\Diactoros\Respnose 的实例，代码位置在 vendor/zendframework/zend-diactoros/src/Response.php，
     * 查看代码我们可以使用 withStatus 方法设置该 Response 的状态码，最后直接返回 Response 即可。
     *
     * */
    public function store(AuthorizationRequest $originRequest, AuthorizationServer $server, ServerRequestInterface $serverRequest)
    {
        try {
            return $server->respondToAccessTokenRequest($serverRequest, new Psr7Response)->withStatus(201);
        } catch(OAuthServerException $e) {
            return $this->response->errorUnauthorized($e->getMessage());
        }
    }
    public function socialStore($type, SocialAuthorizationRequest $request)
    {
        if (!in_array($type, ['weixin'])) {
            return $this->response->errorBadRequest();
        }

        $driver = \Socialite::driver($type);

        try {
            if ($code = $request->code) {
                $response = $driver->getAccessTokenResponse($code);
                $token = array_get($response, 'access_token');
            } else {
                $token = $request->access_token;

                if ($type == 'weixin') {
                    $driver->setOpenId($request->openid);
                }
            }

            $oauthUser = $driver->userFromToken($token);
        } catch (\Exception $e) {
            return $this->response->errorUnauthorized('参数错误，未获取用户信息');
        }

        switch ($type) {
            case 'weixin':
                $unionid = $oauthUser->offsetExists('unionid') ? $oauthUser->offsetGet('unionid') : null;

                if ($unionid) {
                    $user = User::where('weixin_unionid', $unionid)->first();
                } else {
                    $user = User::where('weixin_openid', $oauthUser->getId())->first();
                }

                // 没有用户，默认创建一个用户
                if (!$user) {
                    $user = User::create([
                        'name' => $oauthUser->getNickname(),
                        'avatar' => $oauthUser->getAvatar(),
                        'weixin_openid' => $oauthUser->getId(),
                        'weixin_unionid' => $unionid,
                    ]);
                }

                break;
        }
        /*
         * 调整第三方登录接口 --Passport
         * */
//        $token = Auth::guard('api')->fromUser($user);
//        return $this->respondWithToken($token)->setStatusCode(201);
        $result = $this->getBearerTokenByUser($user, '1', false);
        return $this->response->array($result)->setStatusCode(201);
    }

    protected function respondWithToken($token)
    {
        return $this->response->array([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60
        ]);
    }

//    public function update()
//    {
//        $token = Auth::guard('api')->refresh();
//        return $this->respondWithToken($token);
//    }


    /*
     * 刷新 Token ---passport
     * */
    public function update(AuthorizationServer $server, ServerRequestInterface $serverRequest)
    {
        try {
            return $server->respondToAccessTokenRequest($serverRequest, new Psr7Response);
        } catch(OAuthServerException $e) {
            return $this->response->errorUnauthorized($e->getMessage());
        }
    }
//    public function destroy()
//    {
//        Auth::guard('api')->logout();
//        return $this->response->noContent();
//    }

    /*
     * 删除 Token ---passport
     * */
    public function destroy()
    {
        $this->user()->token()->revoke();
        return $this->response->noContent();
    }
}
