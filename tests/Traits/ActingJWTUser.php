<?php

namespace Tests\Traits;

use App\Models\User;

trait ActingJWTUser
{
    /*
     * 我们发现为用户生成 Token 以及设置 Authorization 部分的代码，
     * 不仅 修改话题，删除话题 会使用，以后编写的其他功能的测试用例一样会使用，所以我们进行一下封装。
     * */
    public function JWTActingAs(User $user)
    {
        $token = \Auth::guard('api')->fromUser($user);
        $this->withHeaders(['Authorization' => 'Bearer '.$token]);

        return $this;
    }
}