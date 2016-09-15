<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Models\User;
use Auth;
use Illuminate\Filesystem\Filesystem;

class MatrixController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', [
            'only' => [
                'getUser',
                'getMenu',
                'getLogout',
            ]
        ]);
    }

    /**
     * 模板锁屏页面
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getLock()
    {
        return view('matrix.lock');
    }

    // ==============  以下为接口功能 ====================

    /**
     * 获取用户对应左侧菜单数据
     * @return mixed
     */
    public function getUser()
    {
        return Auth::user();
    }

    /**
     * 获取用户对应左侧菜单数据
     * @return mixed
     */
    public function getMenu(Filesystem $filesystem)
    {
        // 获取插件列表
        $installed = json_decode($filesystem->get(base_path('vendor/composer/installed.json')), true);

        $collect = collect($installed)->where('type', 'matrix-plugin')->filter(function ($item) {
            // 过滤不显示菜单的插件
            return array_get($item, 'extra.plugin.menu.status', true);
        });

        $groups = $collect->pluck('extra.plugin.menu.group')->toArray();
        $prepend = [];

        $collect->sortBy('time')->each(function ($item) use ($groups, &$prepend) {
            $group = array_get($item, 'extra.plugin.menu.group');
            $icons = array_get($item, 'extra.plugin.menu.group-icons');
            $groupSplit = explode('|', $group);
            $iconsSplit = explode('|', $icons);
            for ($i = count($groupSplit); $i > 1; $i--) {
                $name = array_pop($groupSplit);
                $group = implode('|', $groupSplit);
                $icon = array_pop($iconsSplit);
                $icons = implode('|', $iconsSplit);

                if (!in_array($group, $groups)) {
                    array_push($prepend, [
                        'group' => $group,
                        'name' => $name,
                        'icon' => $icon,
                        'group-icons' => $icons,
                    ]);
                }
            }
        });

        $menus = $collect->map(function ($item) use ($groups) {
            // 菜单路由根据命名空间生成
            $namespace = trim(collect(array_get($item, 'autoload.psr-4', []))->keys()->first(), '\\');
            $namespace = explode('\\', $namespace);
            $url = count($namespace) > 2 ? snake_case($namespace[1]) . '/' . snake_case($namespace[2]) : '#';

            // 生成链接
            array_set($item, 'extra.plugin.menu.url', $url);

            // 24小时内安装的菜单显示new标签
            if (strtotime($item['time'] . '+ 1 day') > time()) {
                $new = [
                    'class' => 'bg-green',
                    'text' => 'new'
                ];
                if (array_get($item, 'extra.plugin.menu.right')) {
                    array_prepend($item, $new);
                } else {
                    array_set($item, 'extra.plugin.menu.right', [$new]);
                }
            }

            return $item;
        })
            ->sortBy('time')
            ->pluck('extra.plugin.menu');

        return collect($prepend)->merge($menus)->all();
    }

    /**
     * 注销登录
     * @return mixed
     */
    public function getLogout()
    {
        return Auth::logout();
    }
}
