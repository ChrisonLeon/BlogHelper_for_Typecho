<?php
namespace TypechoPlugin\BlogHelper;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Widget\Options;
use Utils\Helper;
use Typecho\Common;
use Typecho\Db;

define('__TYPECHO_DEBUG__', true);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}


/**
 * Blog Helper - 一款可以一键同步微信运动、快速发布说说/时光机/碎语/心情等功能的插件。插件作者：<a href="https://chrison.cn" target="_blank">Chrison</a>。插件说明：<a href="https://chrison.cn" target="_blank">Blog Helper文档</a>
 *
 * @package Blog Helper
 * @author Chrison
 * @version 1.2.0
 * @link https://chrison.cn
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate()
    {
        //  php HelloWorldChrison_Plugin::renderFooter(); 
        \Typecho\Plugin::factory('Blog_Helper')->Chrison = array('BlogHelper_Plugin', 'renderFooter');
        
        // 添加后台导航菜单按钮
        \Typecho\Plugin::factory('admin/menu.php')->navBar = __CLASS__ . '::render';
        
        // 添加API路由
        //$name - 路由名称（唯一标识）
        //$url - 路由路径（支持参数占位符）
        //$widget - 处理该路由的 Widget 类名
        //$action - Widget 的动作方法名（可选）
        //$after - 在指定路由之后插入（可选）
        Helper::addRoute('chrison-blog-helper-api', '/api/chrison/blog_help', 'BlogHelper_Action', 'api');
        
        // 创建数据库表
        $db = Db::get();
        $prefix = $db->getPrefix();
        // 创建微信运动记录表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}blog_helper_wechat` (
            `id` int(10) unsigned NOT NULL auto_increment,
            `openid` varchar(32) NOT NULL,
            `steps` int(32) NOT NULL,
            `created` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `created` (`created`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        
        $db->query($sql);
        
        return '插件激活成功，请进入设置页面进行配置！';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate()
    {
        // 卸载API路由
        Helper::removeRoute('chrison-blog-helper-api');
        
        return '插件已禁用';
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
        $pluginUrl = Helper::options()->pluginUrl;
        $qrcode = $pluginUrl . '/BlogHelper/assets/qrcode.jpeg';
        
        // 密钥
        $secret_key = new Text(
            'secret_key',
            NULL,
            Common::randString(32),
            '* 接口保护密钥',
            '<b style="color:red">自动生成。也可以自行填写。请务必设置你的密钥，防止他人非法调用接口<br>将此密钥复制到小程序“我的”=>“插件密钥”输入框中。</b><br> <p style="text-align:center"><img src="'.$qrcode.'" width="180"></p>'
        );
        $form->addInput($secret_key);
        
        
        /** 分类ID */
        $mid = new Text(
            'mid', 
            null, 
            '0', 
            '分类ID',
            '用于小程序快速发布说说/时光机/碎语/心情是的文章所属分类。<br>具体数值从后台“管理”=>“分类”=>进入编辑页面后，查看地址栏mid=XXX，XXX代表具体ID的值'
        );
        $form->addInput($mid);
        
        
        // 是否在后台显示
        $showInBackend = new Radio(
            'showInBackend',
            array(
                '1' => '显示',
                '0' => '隐藏',
            ),
            '1',
            '后台显示',
            '在后台显示微信运动（头部导航处）'
        );
        $form->addInput($showInBackend);
        
        $php_code = '<?php Typecho_Plugin::factory(\'Blog_Helper\')->Chrison(); ?>';
        $show_description = '在前台显示微信运动：页面任意位置插入指定代码即可=> ' . htmlspecialchars($php_code);
        
        // 是否在前台显示
        $showInFront = new Radio(
            'showInFront',
            array(
                '1' => '显示',
                '0' => '隐藏',
            ),
            '1',
            '前台显示',
            $show_description
        );
        $form->addInput($showInFront);
        
        
        // 前端格式化显示
        $frontFormat = new Textarea(
            'frontFormat',
            NULL,
            "<div class='steps'>{steps}</div>
<div class='date'>{date}</div>",
            '前端格式化显示',
            '步数 => {steps}<br>日 => {date}'
        );
        $form->addInput($frontFormat);
        
        
        // 是否在自定义CSS
        $isCustomCss = new Radio(
            'isCustomCss',
            array(
                '0' => '默认',
                '1' => '自定义',
            ),
            '0',
            '自定义样式',
            "是否自定义前端的CSS样式"
        );
        $form->addInput($isCustomCss);
        
        
        // 自定义CSS
        $customCSS = new Textarea(
            'customCSS',
            NULL,
            '.chrison-blog-helper-footer { text-align: center; padding: 20px; margin: 0 auto; color: #666; } 
.chrison-blog-helper-footer .steps{font-size: 24px; font-weight: bold; color: #4CAF50;} 
.chrison-blog-helper-footer .date{font-size: 12px; color: #999;}',
            '自定义CSS',
            '自定义微信运动样式。类名：.chrison-blog-helper-footer、.chrison-blog-helper-footer .steps、.chrison-blog-helper-footer .date'
        );
        $form->addInput($customCSS);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function render()
    {
        $options = Options::alloc()->plugin('BlogHelper');
        $showInBackend = $options->showInBackend;
        
        if($showInBackend === '1'){
            
            $db = Db::get();
            $prefix = $db->getPrefix();
            $step = $db->fetchRow($db->select()->from($prefix.'blog_helper_wechat')->where('1 = ?', 1)->order('created', Db::SORT_DESC));
            
            if($step === null){
                $info = '未获取到数据，请先访问小程序并同步微信运动数据';
            } else {
                $date = date('Y-m-d', strtotime($step['created']));
                $info =  $date.' 运动了 '.$step['steps'].' 步';
            }
            
            echo '<span class="message success">'. $info . '</span>';
        }
    }
    
    public static function renderFooter()
    {
        $options = Options::alloc()->plugin('BlogHelper');
        $sign = $options->sign;
        $showInFront = $options->showInFront;
        $frontFormat = $options->frontFormat;
        $isCustomCss = $options->isCustomCss;
        $defaultCSS = '.chrison-blog-helper-footer { text-align: center; padding: 20px; margin: 0 auto; color: #666; } .chrison-blog-helper-footer .steps{font-size: 24px; font-weight: bold; color: #4CAF50;} .chrison-blog-helper-footer .date{font-size: 12px; color: #999;}';
        
        if($isCustomCss === '0'){
            echo '<style type="text/css">' . "\n" .
                 htmlspecialchars($defaultCSS) . "\n" . 
                 '</style>' . "\n";
        } else {
            $customCSS = $options->customCSS;
            echo '<style type="text/css">' . "\n" .
                 htmlspecialchars($customCSS) . "\n" . 
                 '</style>' . "\n";
        }
        

        if($showInFront === '1'){ 
            
            // 判断格式化数据并处理
            if (empty($frontFormat) || strpos($frontFormat, '{steps}') === false) {
                $html = '{steps}';
            } else {
                $html = $frontFormat;
            }
            
            $db = Db::get();
            $prefix = $db->getPrefix();
            $step_data = $db->fetchRow($db->select()->from($prefix.'blog_helper_wechat')->where('1 = ?', 1)->order('created', Db::SORT_DESC));
            
            if($step_data === null){
                $html = '未获取到数据，请先访问小程序并同步微信运动数据';
            } else {
                $step = $step_data['steps'];
                $date = date('Y-m-d', strtotime($step_data['created']));
                
                // 替换变量
                $html = str_replace(
                    ['{steps}', '{date}'],
                    [$step, $date],
                    $html
                );
            }
            
            echo '<div class="chrison-blog-helper-footer">' . $html . ' </div>';
        }
        
        // 输出插件JS
        //$pluginUrl = Helper::options()->pluginUrl;
        //echo '<script src="' . $pluginUrl . '/BlogHelper/assets/frontend.js?v=' . time() . '"></script>' . "\n";
    }
}
