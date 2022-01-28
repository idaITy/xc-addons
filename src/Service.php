<?php
// +----------------------------------------------------------------------+
// | 插件服务
// +----------------------------------------------------------------------+
// | Author: 村长<idaITy@163.com>
// +----------------------------------------------------------------------+
// | Time: 2022年01月27日 16:16
// +----------------------------------------------------------------------+
namespace xc\addons;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Event;
use think\facade\Lang;
use think\Response;
use think\Route;
use think\route\RuleItem;

class Service extends \think\Service
{
    // -- 插件地址
    private $addons_path;
    /**
     * @desc 注册服务
     * @author: yangsy
     * @time: 2022-01-27 16:22:33
    **/
    public function register()
    {
        $this->addons_path = $this->getAddonsPath();

        // 加载系统语言包
        Lang::load([
            $this->app->getRootPath() . '/vendor/xcadmin/xc-addons/src/lang/zh-cn.php'
        ]);

        // -- 自动加载插件
        $this->autoload();

        // -- 加载插件事件
        $this->loadEvent();

        // -- 加载插件服务
        $this->loadService();

        // -- 绑定插件容器
        $this->app->bind('addons', Service::class);
    }

    /**
     * @desc 服务注册路由
     * @author: yangsy
     * @time: 2022-01-27 22:21:36
    **/
    public function boot()
    {
        // -- 注册路由
        $this->registerRoutes(function(Route $route){
            // 路由脚本
            $execute = '\\xc\\addons\\Route::execute';

            $url_route_must = config('route.url_route_must',false);
            // -- 是否强制开启路由吧！
            if($url_route_must === false){
                // 注册控制器路由
                $route->rule("/:addon/[:module]/[:controller]/[:action]", $execute);
            }
        });
    }

    /**
     * @desc 自动加载插件
     * @author: yangsy
     * @time: 2022-01-27 22:11:48
    **/
    private function autoload(){
        // 是否处理自动载入
        if (!Config::get('addons.autoload', true)) {
            return true;
        }

        $config = Config::get('addons');
        // 读取插件目录及钩子列表
        $base = get_class_methods("\\xc\\addons\\Addons");
        // -- 事件信息
        $listen_array = [];
        foreach(glob($this->getAddonsPath() . '*/*.php') as $addons_file){
            // 格式化路径信息
            $info = pathinfo($addons_file);
            // 获取插件目录名
            $name = pathinfo($info['dirname'], PATHINFO_FILENAME);
            // 找到插件入口文件
            if (strtolower($info['filename']) === 'plugin') {
                // -- 钩子注册事件
                if (file_exists($this->getAddonsPath() . $name . '/config/event.php')) {
                    $addon_event = require_once $this->getAddonsPath() . $name . '/config/event.php';
                    $listen = isset($addon_event['listen']) ? $addon_event['listen'] : [];
                    if (!empty($listen)) {
                        $config['hooks'][] = $listen;
                    }
                }

                // -- 加载自定义路由
                if(file_exists($this->getAddonsPath() . $name . DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'router.php')){
                    include_once $this->getAddonsPath() . $name . DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'router.php';
                }

            }
        }
        // -- 设置config
        Config::set($config, 'addons');
    }

    /**
     * @desc 加载插件事件
     * @author: yangsy
     * @time: 2022-01-27 22:18:23
    **/
    private function loadEvent(){
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);
        if(empty($hooks)){
            $hooks = (array) Config::get('addons.hooks', []);
            if(!empty($hooks)){
                Cache::set('hooks', $hooks);
            }
        }

        // -- 注册事件
        if(!empty($hooks)){
            foreach ($hooks as $k => $listen) {
                if (!empty($listen)) {
                    Event::listenEvents($listen);
                }
            }
        }
        // -- 执行插件初始化
        Event::trigger('AddonInit');
    }

    /**
     * @desc 加载插件服务
     * @author: yangsy
     * @time: 2022-01-27 22:18:31
    **/
    private function loadService(){
        // -- 内容待定，暂时没想法
    }


    /**
     * 获取 addons 路径
     * @return string
     */
    public function getAddonsPath($is_all = true)
    {
        if($is_all == true){
            // 初始化插件目录
            $addons_path = $this->app->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
            // 如果插件目录不存在则创建
            if (!is_dir($addons_path)) {
                @mkdir($addons_path, 0755, true);
            }
        }else{
            $addons_path = 'addons' . DIRECTORY_SEPARATOR;
        }

        return $addons_path;
    }
}