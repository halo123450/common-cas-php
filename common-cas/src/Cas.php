<?php

namespace CommonCas\App;

/**
 * Created by PhpStorm.
 * User: chentengfeng
 * Date: 2019/1/29
 * Time: 8:10 PM
 */
class Cas
{
    const prefixKey = "cas:key:";
    const prefixUserKey = "cas:key:user:";
    private $cache;
    private $session;
    private $system_id;
    private $helper;
    private $sign_key = "";

    /**
     * Cas constructor.
     * @param ICache $cache
     * @param ISession $session
     * @param $cas_url
     * @param $system_id
     * @param Helper|null $helper
     */
    function __construct(ICache $cache, ISession $session, $cas_url, $system_id, Helper $helper = null)
    {
        $this->cache = $cache;
        $this->session = $session;
        $this->system_id = $system_id;

        $helper = is_null($helper) ? new Helper() : $helper;
        $this->helper = $helper;

        CasEnum::$cas_url = $cas_url;
    }

    /**
     * 设置接口请求加密Key
     *
     * @param $sign_key
     * @author jiangxianli
     * @created_at 2019-02-19 14:51
     */
    public function setSignKey($sign_key)
    {
        $this->sign_key = $sign_key;
    }

    /**
     * 是否登录
     * @return bool
     */
    public function hasLogin()
    {
        $user_id = $this->session->get(static::prefixUserKey);
        if (empty($user_id)) {
            return false;
        }

        $cache = $this->cache->get(static::prefixUserKey . $user_id);
        if (!empty($cache)) {
            return true;
        }
    }

    /**
     * 清理
     */
    public function clear()
    {
        $user_id = $this->session->get(static::prefixUserKey);
        if (!empty($user_id)) {
            $this->cleanUser($user_id);
        }

        $this->session->flush();
    }

    /**
     * 清理指定用户
     * @param $user_id
     */
    public function cleanUser($user_id)
    {
        $this->cache->del(static::prefixUserKey . $user_id);
    }

    /**
     * 重定向登录
     * @param $origin_back_url
     * @return string
     */
    public function redirectLogin($origin_back_url)
    {
        $state = $this->helper->genRandStr(10);
        $this->session->set(static::prefixKey . "state", $state);

        $query = [
            "state"        => $state,
            "redirect_url" => $origin_back_url,
        ];
        $params = $this->helper->paramsSignature($query, $this->sign_key);

        return CasEnum::genUrl(CasEnum::auth_pre_store) . "?" . http_build_query($params);
    }

    /**
     * 退出登录
     *
     * @param $redirect_url
     * @param $system_user_id
     * @return string
     * @author jiangxianli
     * @created_at 2019-02-19 14:58
     */
    public function logout($redirect_url, $system_user_id)
    {
        // 通知 cas 服务器
        $this->clear();

        $query = [
            "redirect_url"   => $redirect_url,
            'system_id'      => $this->system_id,
            "system_user_id" => $system_user_id,
        ];
        $params = $this->helper->paramsSignature($query, $this->sign_key);

        return CasEnum::genUrl(CasEnum::auth_logout) . "?" . http_build_query($params);
    }


    /**
     * 回调
     * @param $ticket
     * @param $state
     * @return mixed
     */
    public function callback($ticket, $state)
    {
        $session_state = $this->session->get(static::prefixKey . "state");

        // 该数据有误
        if ($session_state !== $state) {
            throw new CasException(CasException::state, "");
        }

        $query = [
            'ticket'    => $ticket,
            'system_id' => $this->system_id,
        ];
        $params = $this->helper->paramsSignature($query, $this->sign_key);

        $res = $this->helper->curl(CasEnum::genUrl(CasEnum::auth_token), $params);
        if (empty($res)) {
            throw new CasException(CasException::curl_empty, compact("ticket", "state"));
        }

        $response = json_decode($res, true);
        if (isset($response["code"]) && $response["code"] != 0) {
            throw new CasException(
                CasException::curl_date_error,
                compact("ticket", "state", "response")
            );
        }

        $user_id = $response["data"];

        $this->cache->set(static::prefixUserKey . $user_id, $user_id);
        $this->session->set(static::prefixUserKey, $user_id);

        // 当前系统的用户id
        return $user_id;
    }

    /**
     * 绑定用户, user_id 为 cas 中的 user_id, system_user_id 为本系统的用户user_id
     * 若本系统的 user_id 不是唯一，则可以加加上对应的标识符来做唯一
     * 比如 : 呼叫系统，那么则业务的 system_user_id 可以为 sell:123
     * @param $system_user_id
     * @param $user_id
     * @throws CasException
     */
    public function bindUser($system_user_id, $user_id)
    {
        $query = [
            "user_id"            => $user_id,
            "system_user_id"     => $system_user_id,
            "platform_system_id" => $this->system_id,
        ];
        $params = $this->helper->paramsSignature($query, $this->sign_key);
        $res = $this->helper->curl(
            CasEnum::genUrl(CasEnum::auth_bind),
            $params
        );

        if (empty($res)) {
            throw new CasException(
                CasException::curl_empty,
                compact("system_user_id", "user_id")
            );
        }

        $response = json_decode($res, true);

        if (isset($response["code"]) && $response["code"] != 0) {
            throw new CasException(
                CasException::curl_date_error,
                compact("system_user_id", "user_id", "res", "response")
            );
        }

        return $response;
    }

    /**
     * 解绑用户, user_id 为 cas 中的 user_id, system_user_id 为本系统的用户user_id
     * 若本系统的 user_id 不是唯一，则可以加加上对应的标识符来做唯一
     * 比如 : 呼叫系统，那么则业务的 system_user_id 可以为 sell:123
     * @param $system_user_id
     * @param $user_id
     * @param $user_type company_qq:默认 company_wechat:企业微信
     * @throws CasException
     */
    public function unbindUser($system_user_id, $user_type = "company_qq")
    {
        $query = [
            "system_user_id"     => $system_user_id,
            "platform_system_id" => $this->system_id,
            "user_type"          => $user_type,
        ];
        $params = $this->helper->paramsSignature($query, $this->sign_key);
        $res = $this->helper->curl(
            CasEnum::genUrl(CasEnum::auth_unbind),
            $params
        );

        if (empty($res)) {
            throw new CasException(
                CasException::curl_empty,
                compact("system_user_id")
            );
        }

        $response = json_decode($res, true);

        if (isset($response["code"]) && $response["code"] != 0) {
            throw new CasException(
                CasException::curl_date_error,
                compact("system_user_id", "res", "response")
            );
        }

        return $response;
    }

    /**
     * 获取用户列表
     *
     * @param array $query
     * @param $per_page
     * @param $page
     * @return mixed
     * @throws CasException
     * @author jiangxianli
     * @created_at 2019-05-14 13:27
     */
    public function pagingUserList(array $query = [], $per_page, $page)
    {
        $query['per_page'] = $per_page;
        $query['page'] = $page;

        $params = $this->helper->paramsSignature($query, $this->sign_key);
        $res = $this->helper->curl(CasEnum::genUrl(CasEnum::auth_paging_user_list) . "?" . http_build_query($params));
        if (empty($res)) {
            throw new CasException(CasException::curl_empty, compact("query", "per_page", "page"));
        }

        $response = json_decode($res, true);
        if (isset($response["code"]) && $response["code"] != 0) {
            throw new CasException(
                CasException::curl_date_error,
                compact("query", "per_page", "page", "res", "response")
            );
        }

        return $response["data"];
    }
}