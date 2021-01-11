<?php

	// +----------------------------------------------------------------------
	// | 插件event
	// +----------------------------------------------------------------------
	// | Copyright (c) 2015-2022 http://www.yicmf.com, All rights reserved.
	// +----------------------------------------------------------------------
	// | Author: 微尘 <yicmf@qq.com>
	// +----------------------------------------------------------------------

	namespace yicmf\addon\event;

	use yicmf\addon\controller\Controller as AddonController;
	use yicmf\addon\model\Addon as AddonModel;
	use app\common\validate\Addon as AddonValidate;
	use app\common\model\Hook as HookModel;
	use app\admin\model\Menu as MenuModel;
	use app\admin\validate\Menu as MenuValidate;
	use think\facade\App;
	use think\facade\Cache;
	use think\facade\Config;
	use think\Db;
	use think\Exception;
	use think\Loader;
	use yicmf\FileOperation;
	use think\facade\Env;

	class Addon
	{
		/**
		 * 插件目录
		 */
		protected $addon_path;
		/**
		 * 插件静态资源目录
		 */
		protected $addon_static;
		/**
		 * 插件名称
		 */
		protected $addon_name;
		/**
		 * 插件模型.
		 * @var Addon
		 */
		protected $addon_model;
		/**
		 * @var array
		 */
		protected $addon_install_info = [];
		/**
		 * 当前插件对象
		 * @var AddonController
		 */
		protected $addon_obj;

		public function __construct()
		{
			// 默认参数
			$this->addon_install_info = [
				// 新增数据表
				'add_table' => [],
				// sql文件的前缀
				'database_prefix' => '',
				// 钩子增加
				'hook' => [],
				// 应用依赖[可选]，格式[[模块唯一标识, 依赖版本, 对比方式]]
				'need_app' => [],
				// 行为增加
				'action' => [],
				'setting' => [],
				// SEO 规则
				'seo' => []
			];
		}

		/**
		 * 刷新插件列表，一般用于本地上传新的插件或者插件有改动需要更新信息
		 * @return mixed
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:55
		 */
		public function refresh()
		{
			try {
				$dirs = array_map('basename', glob(Env::get('addon_path') . '*', GLOB_ONLYDIR));
				if ($dirs === false || !file_exists(Env::get('addon_path'))) {
					throw new Exception('插件目录不可读或者不存在');
				}
				foreach ($dirs as $value) {
					$this->addon_name = $value;
					// 初始化插件
					$this->initAddonObject();
					$info = $this->addon_obj->getInfo();
					// 获取插件配置
					if (isset($info['identifier'])) {
						$addon = AddonModel::where('identifier', $info['identifier'])->where('status', 'in', '0,1')->find();
						$info['has_config'] = (count($this->addon_obj->getConfig()) > 0) ? 1 : 0;
						if (!$addon) {
							AddonModel::create($info);
						} else {
							// 已经安装的暂不处理
							$addon->save($info);
						}
					}
				}
				$data['code'] = 0;
				$data['message'] = '刷新成功';
			} catch (Exception $e) {
				$data['code'] = 1;
				$data['message'] = $e->getMessage();
			}
			return $data;
		}


		/**
		 * 插件升级
		 * @param string $addon_name 插件name
		 * @return mixed
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:55
		 */
		public function upgrade($addon_name)
		{
			try {
				// 启动事务
				Db::startTrans();
				//实例化插件入口类
				$this->getAddonObject($addon_name);
				//获取model数据
				$this->_initModel($addon_name);
				if (!$this->__isInstall()) {
					throw new Exception('当前插件未安装');
				}
				//执行插件的升级方法
				$this->_upInstall($addon_name);
				//安装插件自己的后台
				$this->_addAddonMenu();
				//复制静态资源
				$this->_copyStatic();
				//配置钩子
				$this->_setHook();
				//配置数据库
				$this->_upDb();
				// 提交事务
				Db::commit();
				$data['code'] = 0;
				$data['message'] = '安装完成';
			} catch (Exception $e) {
				// 回滚事务
				Db::rollback();
				$data['code'] = 1;
				$data['message'] = $e->getMessage();
			}
			return $data;
		}


		/**
		 * 安装插件
		 * @param string $addon_name 插件标识
		 * @return mixed
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:54
		 */
		public function install($addon_name)
		{
			try {
				$this->addon_name = $addon_name;
				// 启动事务
				Db::startTrans();
				//实例化插件入口类
				$this->getAddonObject();
				//加载安装信息
				$this->_getInstallInfo();
				//获取model数据
				$this->_initModel();
				//执行插件的安装方法
				// 数据库安装文件
				$sql_file = $this->addon_path . 'data' . DIRECTORY_SEPARATOR . 'install.sql';
				if (file_exists($sql_file)) {
					$this->_executeSqlFile($sql_file, Config::get('database.prefix'));
				}
				$this->addon_obj->install();
				//安装插件自己的后台
				$this->_addAddonMenu();
				//配置钩子
				$this->_setHook();
				//配置数据库
				$this->_endDb();
				//复制静态资源
				$this->_copyStatic();
				Db::commit();
				$data['code'] = 0;
				$data['message'] = '安装完成';
			} catch (Exception $e) {
				// 回滚事务
				Db::rollback();
				$data['code'] = 1;
				$data['message'] = $e->getMessage();
			}
			return $data;
		}


		/**
		 * 卸载插件
		 * @param string $addon_name 插件name
		 * @return mixed
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:54
		 */
		public function uninstall($addon_name)
		{
			try {
				$this->addon_name = $addon_name;
				// 启动事务
				Db::startTrans();
				//实例化插件入口类
				$this->getAddonObject();
				//获取model数据
				$this->_initModel();
				if (!$this->_isInstall()) {
					throw new Exception('当前插件未安装' . $addon_name);
				}
				//执行插件的安装方法
				$this->addon_obj->uninstall();
				//卸载插件自己的后台
				$this->_removeAddonMenu();
				//移除钩子信息
				$this->_removeHook($addon_name);
				//配置数据库
				$this->_removeDb();
				// 删除插件文件
				$this->_deleteDir($this->addon_path);
				//移除静态资源
				$this->_removeStatic();
				// 提交事务
				Db::commit();
				$data['code'] = 0;
				$data['message'] = '卸载完成';
			} catch (Exception $e) {
				// 回滚事务
				Db::rollback();
				$data['code'] = 1;
				$data['message'] = $e->getMessage();
			}
			return $data;
		}

		/**
		 * 初始化模型数据
		 * @throws Exception
		 * @throws \think\db\exception\DataNotFoundException
		 * @throws \think\db\exception\ModelNotFoundException
		 * @throws \think\exception\DbException
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:53
		 */
		protected function _initModel()
		{
			$this->addon_model = AddonModel::where('name', $this->addon_name)->where('status', 'in', '0,1')->find();;
			if (!$this->addon_model) {
				//新下载，新增相关数据
				$validate = new AddonValidate();
				$data = $this->addon_obj->getInfo();
				$result = $validate->check($data);
				if (true !== $result) {
					throw new Exception('插件数据错误：' . $result);
				} else {
					$this->addon_model = AddonModel::create($data);
					if (!$this->addon_model) {
						throw new Exception('插件数据初始化异常');
					}
				}
			}
		}

		/**
		 * 执行插件的升级方法.
		 * @param string $addon_name
		 * @throws Exception
		 */
		protected function _upInstall($addon_name)
		{
			$up_path = $this->addon_path . 'upgrade' . DIRECTORY_SEPARATOR;
			//SQL升级脚本文件
			$sql_file = $up_path . 'upgrade.sql';
			//php升级文件
			$php_file = $up_path . 'Upgrade.php';

			//判断是否有数据库升级脚本
			if (file_exists($sql_file)) {
				$this->_executeSqlFile($sql_file, Config::get('database.prefix'));
			}
			//判断是否有升级程序脚本
			if (file_exists($php_file)) {
				$class = '\addon\\' . Loader::parseName($addon_name) . '\upgrade\Upgrade';
				if (!class_exists($class, false)) {
					if (!require_once($this->addon_path . 'upgrade' . DIRECTORY_SEPARATOR . 'Upgrade.php')) {
						throw new Exception('引入插件路径异常');
					}
				}
				if (class_exists($class, false)) {
					$upgrade = new $class();
					if (!$upgrade->upgrade()) {
						throw new Exception($upgrade->getError() ?: '执行插件升级脚本错误，升级未完成！');
					}
				}
			}
		}

		/**
		 * 升级插件数据库信息.
		 * @throws Exception
		 */
		protected function _upDb()
		{
			//更新版本号
			$this->addon_model->version = $this->addon_obj->getInfo('version');
			$this->addon_model->save();
		}


		/**
		 * 卸载插件后台管理菜单
		 * @return bool
		 * @throws \think\db\exception\DataNotFoundException
		 * @throws \think\db\exception\ModelNotFoundException
		 * @throws \think\exception\DbException
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:43
		 */
		protected function _removeAddonMenu()
		{
			if (!$this->addon_model['has_adminlist']) {
				return true;
			}
			Cache::rm('system_menus_lists');
			//删除对应菜单
			$has_menu = MenuModel::where('status', 1)->where('url', 'admin/Addon/adminList?name=' . $this->addon_model['name'])->find();
			if ($has_menu) {
				$has_menu->status = -2;
				$has_menu->save();
			}
			//权限控制
		}

		/**
		 * 移除静态资源
		 * @throws Exception
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:41
		 */
		protected function _removeStatic()
		{
			if (file_exists($this->addon_static)) {
				$dir = new FileOperation();
				$dir->delDir($this->addon_static);
			}
		}

		/**
		 * 移除钩子信息
		 * @param $addon_name
		 * @throws \think\db\exception\DataNotFoundException
		 * @throws \think\db\exception\ModelNotFoundException
		 * @throws \think\exception\DbException
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:25
		 */
		protected function _removeHook($addon_name)
		{
			$hook_lists = $this->_getHooks();
			$hooks = HookModel::where('name', 'in', $hook_lists)->where('status', 1)->select();
			foreach ($hooks as $hook) {
				if (in_array($addon_name, $hook['addons'])) {
					$addons = $hook['addons'];
					$key = array_search($addon_name, $addons);
					if ($key !== false) {
						array_splice($addons, $key, 1);
						$hook['addons'] = $addons;
						$hook->save();
					}
				};
			}
		}

		/**
		 * 获取当前插件的所使用的钩子列表
		 * @return array|mixed
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:25
		 */
		protected function _getHooks()
		{
			// 是否有专门的钩子文件
			if (!empty($this->addon_install_info['hook'])) {
				$hook_lists = $this->addon_install_info['hook'];
			} else {
				$hook_lists = array_diff(get_class_methods($this->addon_obj), ['install', 'uninstall', '__construct', 'getConfig', 'setConfig', 'getInfo', 'getBuilder']);
				foreach ($hook_lists as $key => $value) {
					$hook_lists[$key] = Loader::parseName($value);
				}
			}
			return $hook_lists;
		}

		/**
		 * 删除插件数据库信息
		 * @throws Exception
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:24
		 */
		protected function _removeDb()
		{
			$this->addon_model['status'] = -2;
			if (!$this->addon_model->save()) {
				throw new Exception($this->addon_model->getError() ?: '插件信息删除异常');
			}
		}

		/**
		 * 更新插件为安装状态
		 * @throws Exception
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:23
		 */
		protected function _endDb()
		{
			$this->addon_model->status = 1;
			if (!$this->addon_model->save()) {
				throw new Exception('数据库保存异常！');
			}
		}

		/**
		 * 复制静态资源
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:52
		 */
		protected function _copyStatic()
		{
			if (file_exists($this->addon_path . 'static')) {
				$dir = new FileOperation();
				$dir->copyDir($this->addon_path . 'static', $this->addon_static);
			}
		}

		/**
		 * 配置钩子
		 * @throws \think\db\exception\DataNotFoundException
		 * @throws \think\db\exception\ModelNotFoundException
		 * @throws \think\exception\DbException
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:22
		 */
		protected function _setHook()
		{
			//检查需要的钩子是否齐全
			$hook_lists = $this->_getHooks();
			$model_Hook = new HookModel();
			$insertAll = [];
			foreach ($hook_lists as $key => $hook) {
				// 查询是否已经存在
				if (is_array($hook) && !$model_Hook::where('name', $hook['name'])->where('status', 1)->find()) {
					// 新增
					$hook['addons'][] = $this->addon_name;
					$insertAll[] = $hook;
				} else {
					// 现有的钩子，增加
					if (is_numeric($key)) {
						$hook_name = $hook;
					} else {
						$hook_name = $hook['name'];
					}
					$hook = HookModel::where('name', $hook_name)->where('status', 1)->find();
					if (!$hook) {
						throw new \Exception('钩子缺失，无法安装当前插件');
					}
					$addons = $hook['addons'];
					array_push($addons, $this->addon_name);
					$hook['addons'] = $addons;
					$hook->save();
				}
				if (!empty($insertAll)) {
					$model_Hook->saveAll($insertAll);
				}
			}
		}

		/**
		 * 获取插件对象
		 * @return AddonController
		 * @throws Exception
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:22
		 */
		protected function getAddonObject()
		{
			//当前插件根目录
			$this->addon_path = Env::get('addon_path') . Loader::parseName($this->addon_name) . DIRECTORY_SEPARATOR;
			$this->addon_static = Env::get('addon_static') . Loader::parseName($this->addon_name) . DIRECTORY_SEPARATOR;
			$na_class = get_addon_class($this->addon_name);
			if (!class_exists($na_class, false)) {
				if (!is_file($this->addon_path . Loader::parseName($this->addon_name, 1) . '.php') || !require_once($this->addon_path . Loader::parseName($this->addon_name, 1) . '.php')) {
					throw new Exception('引入插件路径异常');
				}
			}
			if (class_exists($na_class, false)) {
				//导入对应插件
				$this->addon_obj = new $na_class();
			} else {
				throw new Exception('获取插件对象出错：' . $na_class);
			}
			return $this->addon_obj;
		}

		/**
		 * 加载安装信息
		 */
		protected function _getInstallInfo()
		{
			//当前插件根目录
			$file = Env::get('addon_path') . Loader::parseName($this->addon_name) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'install' . App::getConfigExt();
			if (is_file($file)) {
				$this->addon_install_info = array_merge($this->addon_install_info, include $file);
			}
		}

		/**
		 * 检查插件是否已经安装.
		 * @return bool
		 */
		protected function _isInstall()
		{
			return $this->addon_model->status > 0 ? true : false;
		}

		/**
		 * 添加插件后台管理菜单
		 * @return bool
		 * @throws Exception
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:20
		 */
		protected function _addAddonMenu()
		{
			if (!$this->addon_model['has_adminlist']) {
				return true;
			}
			$info = $this->addon_obj->getInfo();
			//查询出“扩展列表”菜单ID
			$p_menu = MenuModel::where('controller', 'Addon')
				->where('action', 'root')
				->where('status', 1)
				->where('pid', 0)
				->find();
			//get(['title' => '扩展', 'controller' => 'Addon', 'action' => 'index', 'status' => 1]);
			if (!$p_menu) {
				throw new Exception('获取扩展菜单id错误！');
			}
			//检查是否已经安装该菜单
			$has_menu = MenuModel::where('status', 1)->where('url', 'admin/Addon/adminList?name=' . $this->addon_model['name'])->find();
			if ($has_menu) {
				$has_menu->status = -2;
				$has_menu->save();
			}
			//添加插件后台
			$data = [
				//父ID
				'pid' => $p_menu->id,
				//模块目录名称，也是项目名称
				'title' => $info['title'],
				//插件名称
				'controller' => $info['name'],
				'module' => 'admin',
				'controller' => 'Addon',
				//方法名称
				'action' => 'index',
				'url' => 'admin/Addon/adminList?name=' . $info['name'],
				//备注
				'tip' => $info['title'] . '插件管理后台',
				//排序
				'group' => '已经安装插件',
				//是否开发者
				'is_dev' => $info['is_dev'],
				//应用图标
				'icon' => $info['icon'],
			];
			// 验证插件数据
			$menuValidate = new MenuValidate();
			$result = $menuValidate->check($data);
			if (true !== $result) {
				throw new Exception('插件菜单数据错误:' . $result);
			}
			$menu = MenuModel::create($data);
			if (!$menu) {
				throw new Exception('添加插件后台错误');
			} else {
				Cache::rm('system_menus_lists');
			}
		}

		/**
		 * 删除文件
		 * @param $dir
		 * @throws Exception
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:21
		 */
		protected function _deleteDir($dir)
		{
			$file = new FileOperation();
			// 删除目录
			$file->delDir($dir);
		}

		/**
		 * 执行SQL文件.
		 * @param string $file 要执行的sql文件路径
		 * @param string $database_prefix 前缀优化
		 * @param string $db_charset 数据库编码 默认为utf-8
		 * @throws Exception
		 * @author  : 微尘 <yicmf@qq.com>
		 * @datetime: 2019/4/12 15:21
		 */
		protected function _executeSqlFile($file, $database_prefix, $db_charset = 'utf-8')
		{
			$error = true;
			if (!is_readable($file)) {
				throw new Exception('SQL文件不可读');
			}
			$fp = fopen($file, 'rb');
			$sql = fread($fp, filesize($file));
			fclose($fp);
			$sql = str_replace("\r", "\n", str_replace('`' . 'q7_', '`' . $database_prefix, $sql));
			foreach (explode(";\n", trim($sql)) as $query) {
				$query = trim($query);
				if ($query) {
					if (Db::execute($query) === false) {
						throw new Exception('SQL文件不可读');
					}
				}
			}
		}
	}
