<?php
/**
 * Created by PhpStorm.
 * User: chentengfeng
 * Date: 2019/1/29
 * Time: 8:26 PM
 */

namespace CommonCas\App;


class CasEnum
{
    static $cas_url = "";

    // 重定向保存信息
    const auth_pre_store = "/web/cas/auth/pre-store";

    // 重定向 退出登录
    const auth_logout = "/v1/cas/auth/logout";

    // 通过 ticket 获取对应的用户信息
    const auth_token = "/v1/cas/auth/ticket";

    // 绑定用户管理
    const auth_bind = "/v1/cas/entity/user-platform-system";

    // 分页获取用户列表
    const auth_paging_user_list = "/v1/cas/entity/user-list";

    // 解绑用户管理
    const auth_unbind = "/v1/cas/entity/user-platform-system-del";

    /**
     * 生成指定链接
     * @param $urlPath
     * @return string
     */
    public static function genUrl($urlPath)
    {
        return sprintf(
            "%s%s",
            static::$cas_url,
            $urlPath
        );
    }
}