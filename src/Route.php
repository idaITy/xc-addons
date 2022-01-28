<?php
// +----------------------------------------------------------------------+
// | 路由脚本
// +----------------------------------------------------------------------+
// | Author: 村长<idaITy@163.com>
// +----------------------------------------------------------------------+
// | Time: 2022年01月28日 9:50
// +----------------------------------------------------------------------+
namespace xc\addons;

use think\exception\HttpException;
use think\facade\Config;
use think\facade\Event;
use think\helper\Str;

class Route
{
    /**
     * @desc 插件路由请求
     * @author: yangsy
     * @time: 2022-01-28 09:56:39
     **/
    public static function execute($addon = null, $module = null, $controller = null, $action = null)
    {
        $app = app();
        $request = $app->request;

        // -- 执行插件绑定事件
        Event::trigger('addons_begin', $request);
        // -- 验证参数
        if (empty($module) || empty($addon) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('addon can not be empty'));
        }
        $request->addon = $addon;
        // 设置当前请求的控制器、操作
        $request->setController($controller)->setAction($action);

        // 获取插件基础信息
        $info = get_addons_info($addon);

        // -- 判断插件信息
        if (!$info) {
            throw new HttpException(404, lang('addon %s not found', [$addon]));
        }
        if (!$info['status']) {
            throw new HttpException(500, lang('addon %s is disabled', [$addon]));
        }

        // 监听addon_module_init
        Event::trigger('addon_module_init', $request); // 插件运行

        // -- 获取controller类
        $class = get_addons_class($addon, 'controller', $controller,$module);
        if (!$class) {
            throw new HttpException(404, lang('addon controller %s not found', [Str::studly($controller)]));
        }

        // 重写视图基础路径
        $config = Config::get('view');
        $config['view_dir_name'] = $app->addons->getAddonsPath() . $addon . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
        $s = Config::set($config, 'view');

        // 生成控制器对象
        $instance = new $class($app);
        $vars = [];
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call = [$instance, '_empty'];
            $vars = [$action];
        } else {
            // 操作不存在
            throw new HttpException(404, lang('addon action %s not found', [get_class($instance).'->'.$action.'()']));
        }

        Event::trigger('addons_action_begin', $call); // 插件结束运行
        return call_user_func_array($call, $vars);
    }
}