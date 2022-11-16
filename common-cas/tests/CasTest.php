<?php

namespace CommonCas\Tests;

use CommonCas\App\CasEnum;
use CommonCas\App\CasException;
use CommonCas\App\Helper;
use CommonCas\App\ICache;
use CommonCas\App\ISession;
use PHPUnit\Framework\TestCase;
use CommonCas\App\Cas;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Created by PhpStorm.
 * User: chentengfeng
 * Date: 2019/1/30
 * Time: 11:10 AM
 */
class CasTest extends TestCase
{
    /**
     * @var MockObject
     */
    protected $cache;
    /**
     * @var MockObject
     */
    protected $session;
    /**
     * @var MockObject
     */
    protected $helper;


    protected function setUp()
    {
        $this->session = $this->getMockBuilder(ISession::class)
            ->setMethods(["get", "set", "flush"])
            ->getMock();

        $this->cache = $this->getMockBuilder(ICache::class)
            ->setMethods(["get", "set", "del"])
            ->getMock();

        $this->helper = $this->getMockBuilder(Helper::class)
            ->setMethods(["curl", "genRandStr", "genSign", "trimAny", "getNow", "paramsSignature"])
            ->getMock();
    }


    /**
     * 正常有登录状态
     */
    public function testHasLogin()
    {
        $user_id = 123;

        // session 会被调用一次，并且传入的值为Cas::prefixUserKey, 并且返回 123
        $this->session->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixUserKey))
            ->willReturn($user_id);

        // session 会被调用一次，并且传入的值为Cas::prefixUserKey, 并且返回 123
        $this->cache->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixUserKey . $user_id))
            ->willReturn($user_id);

        $cas = new Cas($this->cache, $this->session, "", "");

        $this->assertTrue($cas->hasLogin());
    }

    /**
     * 当前 session 没有登录
     */
    public function testHasLoginNotSession()
    {
        $this->session->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixUserKey))
            ->willReturn("");


        $this->cache->expects($this->exactly(0))
            ->method("get");

        $cas = new Cas($this->cache, $this->session, "", "");

        $this->assertNotTrue($cas->hasLogin());
    }

    /**
     * 当前 cache 已经被删除
     */
    public function testHasLoginNotCache()
    {
        $user_id = 123;
        $this->session->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixUserKey))
            ->willReturn($user_id);

        $this->cache->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixUserKey . $user_id))
            ->willReturn("");

        $cas = new Cas($this->cache, $this->session, "", "");

        $this->assertNotTrue($cas->hasLogin());
    }

    /**
     * 删除指定用户信息
     */
    public function testClearUser()
    {
        $user_id = 123;
        $this->cache->expects($this->once())
            ->method("del")
            ->with($this->equalTo(Cas::prefixUserKey . $user_id));

        $cas = new Cas($this->cache, $this->session, "", "");

        $cas->cleanUser($user_id);
    }

    /**
     * 清理数据
     * @depends testClearUser
     */
    public function testClear()
    {
        $user_id = 123;
        $this->session->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixUserKey))
            ->willReturn($user_id);

        $this->cache->expects($this->once())
            ->method("del")
            ->with($this->equalTo(Cas::prefixUserKey . $user_id));

        $this->session->expects($this->once())
            ->method("flush");


        $cas = new Cas($this->cache, $this->session, "", "");
        $cas->clear();
    }

    /**
     * 清空用户
     * @depends testClearUser
     */
    public function testClearEmptyUser()
    {
        $user_id = "";
        $this->session->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixUserKey))
            ->willReturn($user_id);

        $this->session->expects($this->once())
            ->method("flush");

        // 不会调用到
        $this->cache->expects($this->exactly(0))
            ->method("del");

        $cas = new Cas($this->cache, $this->session, "", "");
        $cas->clear();
    }

    /**
     * 测试重定向登录
     */
    public function testRedirectLogin()
    {
        $state = "asdfasdzxv";
        $origin_redirect_url = "";
        $sign_key = "";
        $this->helper->expects($this->once())
            ->method("genRandStr")
            ->with($this->equalTo(10))
            ->willReturn($state);

        $this->session->expects($this->once())
            ->method("set")
            ->with(
                $this->equalTo(Cas::prefixKey . "state"),
                $this->equalTo($state)
            );

        $query = [
            "state"        => $state,
            "redirect_url" => $origin_redirect_url,
        ];
        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn([]);

        $cas = new Cas($this->cache, $this->session, "", "", $this->helper);
        $cas->setSignKey($sign_key);
        $this->assertNotEmpty($cas->redirectLogin($origin_redirect_url));
    }

    /**
     * 退出登录
     *
     * @author jiangxianli
     * @created_at 2019-02-20 17:07
     */
    public function testLogout()
    {
        $redirect_url = "";
        $system_user_id = 1;
        $system_id = 1;
        $sign_key = "";

        $query = [
            "redirect_url"   => $redirect_url,
            'system_id'      => $system_id,
            "system_user_id" => $system_user_id,
        ];
        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn([]);

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->setSignKey($sign_key);
        $this->assertNotEmpty($cas->logout($redirect_url, $system_user_id));
    }

    /**
     * 正常回调
     */
    public function testCallback()
    {
        $session_state = "asdfasdzxv";
        $ticket = "asdfasdfasdf";
        $system_id = 123;
        $user_id = "123123";
        $response = json_encode([
            "data" => $user_id,
            "code" => 0,
            "msg"  => "成功",
        ]);


        $this->session->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixKey . "state"))
            ->willReturn($session_state);

        $query = [
            'ticket'    => $ticket,
            'system_id' => $system_id,
        ];
        $params = $this->helper->paramsSignature($query, "");

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_token)),
                $this->equalTo($params)
            )
            ->willReturn($response);

        $this->cache->expects($this->once())
            ->method("set")
            ->with(
                $this->equalTo(Cas::prefixUserKey . $user_id),
                $this->equalTo($user_id)
            );
        $this->session->expects($this->once())
            ->method("set")
            ->with(
                $this->equalTo(Cas::prefixUserKey),
                $this->equalTo($user_id)
            );

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);

        $this->assertEquals($cas->callback($ticket, $session_state), $user_id);
    }

    /**
     * 测试回调的异常情况
     * @expectedException \CommonCas\App\CasException
     */
    public function testCallbackNotState()
    {
        $input_state = "asdfasdf";
        $session_state = "asdfasdzxv";
        $ticket = "asdfasdfasdf";
        $system_id = 123;


        $this->session->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixKey . "state"))
            ->willReturn($session_state);

        $this->helper->expects($this->exactly(0))
            ->method("curl");

        $this->cache->expects($this->exactly(0))
            ->method("set");
        $this->session->expects($this->exactly(0))
            ->method("set");

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->callback($ticket, $input_state);
    }

    /**
     * 回调请求时的空返回
     * @expectedException \CommonCas\App\CasException
     */
    public function testCallbackEmptyCurl()
    {
        $session_state = "asdfasdzxv";
        $ticket = "asdfasdfasdf";
        $system_id = 123;

        $this->session->expects($this->once())
            ->method("get")
            ->with($this->equalTo(Cas::prefixKey . "state"))
            ->willReturn($session_state);

        $query = [
            'ticket'    => $ticket,
            'system_id' => $system_id,
        ];
        $params = $this->helper->paramsSignature($query, "");

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_token)),
                $this->equalTo($params)
            )
            ->willReturn("");

        $this->cache->expects($this->exactly(0))
            ->method("set");
        $this->session->expects($this->exactly(0))
            ->method("set");

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->callback($ticket, $session_state);
    }

    /**
     * 测试正常绑定用户
     */
    public function testBindUser()
    {
        $system_user_id = "sell:123";
        $user_id = 123123;
        $system_id = 123;
        $sign_key = "";
        $response = json_encode([
            "data" => "",
            "code" => 0,
            "msg"  => "成功",
        ]);

        $query = [
            "user_id"            => $user_id,
            "system_user_id"     => $system_user_id,
            "platform_system_id" => $system_id,
        ];

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_bind)),
                $this->equalTo($query)
            )
            ->willReturn($response);

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->bindUser($system_user_id, $user_id);
    }

    /**
     * 测试返回数据为空时的处理
     * @expectedException \CommonCas\App\CasException
     */
    public function testBindUserEmptyCurl()
    {
        $system_user_id = "sell:123";
        $user_id = 123123;
        $system_id = 123;

        $query = [
            "user_id"            => $user_id,
            "system_user_id"     => $system_user_id,
            "platform_system_id" => $system_id,
        ];
        $sign_key = "";

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_bind)),
                $this->equalTo($query)
            )
            ->willReturn("");

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->bindUser($system_user_id, $user_id);
    }

    /**
     * 测试返回数据code不为0时的处理
     * @expectedException \CommonCas\App\CasException
     */
    public function testBindUserCodeNeqZero()
    {
        $system_user_id = "sell:123";
        $user_id = 123123;
        $system_id = 123;
        $response = json_encode([
            "data" => "",
            "code" => 10000,
            "msg"  => "参数错误",
        ]);

        $query = [
            "user_id"            => $user_id,
            "system_user_id"     => $system_user_id,
            "platform_system_id" => $system_id,
        ];
        $sign_key = "";

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_bind)),
                $this->equalTo($query)
            )
            ->willReturn($response);

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->bindUser($system_user_id, $user_id);
    }

    /**
     * 测试正常获取分页用户列表
     */
    public function testPagingUserList()
    {
        $name = "123";
        $per_page = 10;
        $page = 1;
        $system_id = 123;
        $keywords = 123;
        $sign_key = "";
        $query = [
            "keywords" => $keywords,
            "per_page" => $per_page,
            "page"     => $page,
        ];
        $check = [
            [
                "id"         => 123,
                "name"       => "张三",
                "sex"        => "male",
                "realname"   => "",
                "created_at" => "2019-01-02 09:08:09",
                "updated_at" => "2019-01-02 09:08:09",
            ]
        ];
        $response = json_encode([
            "code" => 0,
            "msg"  => "成功",
            "data" => $check,
        ]);

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_paging_user_list) . "?" . http_build_query($query))
            )
            ->willReturn($response);

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);

        $this->assertEquals(
            $check,
            $cas->pagingUserList(['keywords' => $keywords], $per_page, $page)
        );
    }

    /**
     * 测试返回数据为空的处理
     * @expectedException \CommonCas\App\CasException
     */
    public function testPagingUserListEmptyCurl()
    {
        $per_page = 10;
        $page = 1;
        $system_id = 123;
        $sign_key = "";
        $keywords = 123;
        $query = [
            "keywords" => $keywords,
            "per_page" => $per_page,
            "page"     => $page,
        ];

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_paging_user_list) . "?" . http_build_query($query))
            )
            ->willReturn("");

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);

        $cas->pagingUserList(['keywords' => $keywords], $per_page, $page);
    }

    /**
     * 测试返回数据code不为0时的处理
     * @expectedException \CommonCas\App\CasException
     */
    public function testPagingUserListCodeNeqZero()
    {
        $keywords = 123;
        $per_page = 10;
        $page = "";
        $system_id = 123;
        $sign_key = "";
        $query = [
            "keywords" => $keywords,
            "per_page" => $per_page,
            "page"     => $page,
        ];
        $response = json_encode([
            "code" => 10000,
            "msg"  => "参数错误",
            "data" => "",
        ]);

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_paging_user_list) . "?" . http_build_query($query)),
                $this->equalTo(null),
                $this->equalTo(array()),
                $this->equalTo(10)
            )
            ->willReturn($response);

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->setSignKey($sign_key);

        $cas->pagingUserList(array('keywords' => $keywords), $per_page, $page);
    }



    /**
     * 测试正常解绑用户
     */
    public function testUnbindUser()
    {
        $system_user_id = "sell:123";
        $system_id = 123;
        $sign_key = "";
        $response = json_encode([
            "data" => "",
            "code" => 0,
            "msg"  => "成功",
        ]);

        $query = [
            "system_user_id"     => $system_user_id,
            "platform_system_id" => $system_id,
        ];

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_unbind)),
                $this->equalTo($query)
            )
            ->willReturn($response);

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->unbindUser($system_user_id);
    }

    /**
     * 测试解绑用户返回数据code不为0时的处理
     * @expectedException \CommonCas\App\CasException
     */
    public function testUnbindUserCodeNeqZero()
    {
        $system_user_id = "sell:123";
        $system_id = 123;
        $response = json_encode([
            "data" => "",
            "code" => 10000,
            "msg"  => "参数错误",
        ]);

        $query = [
            "system_user_id"     => $system_user_id,
            "platform_system_id" => $system_id,
        ];
        $sign_key = "";

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_unbind)),
                $this->equalTo($query)
            )
            ->willReturn($response);

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->unbindUser($system_user_id);
    }

    /**
     * 测试解绑用户返回数据为空时的处理
     * @expectedException \CommonCas\App\CasException
     */
    public function testUnbindUserEmptyCurl()
    {
        $system_user_id = "sell:123";
        $system_id = 123;

        $query = [
            "system_user_id"     => $system_user_id,
            "platform_system_id" => $system_id,
        ];
        $sign_key = "";

        $this->helper->expects($this->once())
            ->method("paramsSignature")
            ->with(
                $this->equalTo($query),
                $this->equalTo($sign_key)
            )->willReturn($query);

        $this->helper->expects($this->once())
            ->method("curl")
            ->with(
                $this->equalTo(CasEnum::genUrl(CasEnum::auth_unbind)),
                $this->equalTo($query)
            )
            ->willReturn("");

        $cas = new Cas($this->cache, $this->session, "", $system_id, $this->helper);
        $cas->unBindUser($system_user_id);
    }
}