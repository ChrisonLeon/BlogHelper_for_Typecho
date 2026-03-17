<?php
namespace TypechoPlugin\BlogHelper;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Widget\Options;
use Utils\Helper;
use Typecho\Common;
use Typecho\Db;

define('__TYPECHO_DEBUG__', true);

if (!defined('__TYPECHO_GRAVATAR_PREFIX__')) {
    // 如果未定义，则进行定义
    define('__TYPECHO_GRAVATAR_PREFIX__', 'https://cn.cravatar.com/avatar/');
}
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}


/**
 * Blog Helper - 一款可以一键同步微信运动、快速发布说说/时光机/碎语/心情等功能的插件。插件作者：<a href="https://chrison.cn" target="_blank">Chrison</a>。插件说明：<a href="https://chrison.cn/work/369.html" target="_blank">Blog Helper文档</a>
 *
 * @package Blog Helper
 * @author Chrison
 * @version 1.3.5
 * @link https://chrison.cn/work/369.html
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate()
    {
        
        \Typecho\Plugin::factory('admin/menu.php')->navBar = __CLASS__ . '::renderBack';
        
        \Typecho\Plugin::factory('Blog_Helper')->ChrisonFull = [__CLASS__, 'renderFront'];
        
        \Typecho\Plugin::factory('Blog_Helper')->ChrisonStatus = [__CLASS__, 'renderStatus'];
        
        \Typecho\Plugin::factory('Blog_Helper')->ChrisonAlone = [__CLASS__, 'renderAlone'];
        
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
        
        // 创建状态记录表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}blog_helper_status` (
            `id` int(10) unsigned NOT NULL auto_increment,
            `openid` varchar(32) NOT NULL,
            `emojiId` varchar(32) NOT NULL,
            `emojiName` varchar(32) NOT NULL,
            `customText` varchar(32) NOT NULL,
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
        
        
        $status_code = '<?php Typecho_Plugin::factory(\'Blog_Helper\')->ChrisonStatus(); ?>';
        $status_code = '在网站任意页面任意位置位置插入代码=> ' . htmlspecialchars($status_code);
        
        $full_code = '<?php Typecho_Plugin::factory(\'Blog_Helper\')->ChrisonFull(); ?>';
        $full_code = '<br>在网站任意页面任意位置位置插入代码=> ' . htmlspecialchars($full_code);
        
        $alone_code = '<?php $alone_code = Typecho_Plugin::factory(\'Blog_Helper\')->ChrisonAlone(); ?>';
        $alone_code = '在网站任意页面任意位置位置插入代码=> ' . htmlspecialchars($alone_code);
        
        // 密钥
        $secret_key = new Text(
            'secret_key',
            NULL,
            Common::randString(32),
            '接口保护密钥',
            '<b style="color:red">自动生成。也可以自行填写。请务必设置你的密钥，防止他人非法调用接口<br>将此密钥复制到小程序“我的”=>“插件密钥”输入框中。</b>
            <br> 
            <p style="text-align:center"><img src="'.$qrcode.'" width="180"></p>'
        );
        $form->addInput($secret_key);
        
        
        /** 分类ID */
        $mid = new Text(
            'mid', 
            null, 
            '0', 
            '分类ID',
            '用于小程序快速发布说说/时光机/碎语/心情是的文章所属分类。<br>具体数值从后台“管理”=>“分类”=>进入编辑页面后，查看地址栏mid=XXX，XXX代表具体ID的值
            <br>
            <h2 style="text-align:center">状态气泡挂件的使用</h2>'
        );
        $form->addInput($mid);
        
        // 是否在网站底部显示“我的状态”
        $showMyStatus = new Radio(
            'showMyStatus',
            array(
                '1' => '显示',
                '0' => '隐藏',
            ),
            '1',
            '在网站底部显示',
            '状态图标和文字由小程序设置后推送显示<br>'.$status_code
        );
        $form->addInput($showMyStatus);
        
        $showTime = new Text(
        'showTime', NULL,
        '6',
        _t('显示时长'),
        _t('
            1. 输入显示状态气泡的时长（单位：小时）<br/>
            2. 输入 1 则间隔时长为 1 小时。<br/>
            3. 输入 8 则间隔时长为 8 小时。<br/>
            <h2 style="text-align:center">微信运动步数、状态文字和图片的获取</h2>
        '));
        $form->addInput($showTime);
        
        
        // 方式一：变量参数替换
        $frontFormat = new Textarea(
            'frontFormat',
            NULL,
            Plugin::paramsCode(),
            '方式一：变量参数替换并格式化',
            '微信步数 => {step_num}&nbsp;&nbsp;步数更新日期 => {step_date}&nbsp;状态图片 => {status_pic_url}&nbsp;状态文字 => {status_text}
            '.$full_code
        );
        $form->addInput($frontFormat);
        
        // 方式二：独立获取并使用
        $otherData1 = new Textarea(
            'otherData1',
            NULL,
            '插入下面代码后，获得参数$alone_code，可按需获取‘微信步数’、‘同步时间’、‘当前状态’、‘状态图片’、‘状态时间’
  <?php 
    $alone_code = Typecho_Plugin::factory(\'Blog_Helper\')->ChrisonAlone(); 
    echo \'微信步数：\'.$alone_code[\'step_num\'].\'<br>\';
    echo \'同步时间：\'.$alone_code[\'step_short_date\'].\'|\'.$alone_code[\'step_full_date\'].\'<br>\';
    echo \'当前状态：\'.\'<img src=\'.$alone_code[\'status_pic_url\'].\' width="28" style="filter: invert(80%);">\'.$alone_code[\'status_text\'].\'<br>\';
    echo \'同步时间：\'.$alone_code[\'status_short_date\'].\'|\'.$alone_code[\'status_full_date\'].\'<br>\';
  ?>
            ',
            '方式二：独立获取并使用',
            $alone_code
        );
        $form->addInput($otherData1);
        
        // 自定义CSS
        $customCSS = new Textarea(
            'customCSS',
            NULL,
            Plugin::cssCode(),
            '自定义CSS',
            '自定义样式'
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
     * 插件实现方法 - 管理后台
     *
     * @access public
     * @return void
     */
    public static function renderBack()
    {
        $options = Options::alloc()->plugin('BlogHelper');
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
    
    /**
     * 插件实现方法 - 网站前端
     *
     * @access public
     * @return void
     */
    public static function renderFront(){
        $options = Options::alloc()->plugin('BlogHelper');
        $frontFormat = $options->frontFormat;
        $customCSS = $options->customCSS;
        

        // 获取管理员信息
        $user = Widget::widget('Widget_Users_Admin@uid=' . '1');
        // 插件地址
        $Plugin_Url = Helper::options()->pluginUrl .'/BlogHelper/';
        // 数据库链接
        $db = Db::get();
        $prefix = $db->getPrefix();
        // 状态数据
        $status_data = $db->fetchRow($db->select()->from($prefix.'blog_helper_status')->where('1 = ?', 1)->order('created', Db::SORT_DESC));
        // 微信数据
        $step_data = $db->fetchRow($db->select()->from($prefix.'blog_helper_wechat')->where('1 = ?', 1)->order('created', Db::SORT_DESC));
        // 变量数据
        $step_num = '';
        $step_date = '';
        $status_text = '';
        $status_pic_url = '';
        if($step_data !== null){
            $step_num = $step_data['steps'] ?? '';
            $step_date = date('Y-m-d', strtotime($step_data['created']??'1970-01-01'));
        }
        if($status_data !== null){
            $emojiId = $status_data['emojiId'];
            $emojiName = $status_data['emojiName'];
            $customText = $status_data['customText'];
            $created_time = strtotime($status_data['created'] ?? '1970-01-01');
            $status_date = date('Y-m-d', $created_time);
            $status_text = !empty($customText) ? $customText : $emojiName;
            $status_pic_url = Plugin::iconUrl($emojiId);
        }
        
        if(!empty($customCSS)){
            echo '<style type="text/css">' . "\n" .
                 htmlspecialchars($customCSS) . "\n" . 
                 '</style>' . "\n";
        }
        
        // 替换变量
        $html = str_replace(
            ['{step_num}', '{step_date}', '{status_text}', '{status_pic_url}'],
            [$step_num, $step_date, $status_text, $status_pic_url],
            $frontFormat
        );

        echo '<div class="chrison-blog-helper-full">' . $html . ' </div>';
            
    }
    
    public static function renderAlone()
    {
        $result = [
            "step_num" => "",
            "step_short_date" => "",
            "step_full_date" => "",
            "status_text" => "",
            "status_pic_url" => "",
            "status_short_date" => "",
            "status_full_date" => "",
        ];
        
        // 获取系统配置
        $options = Helper::options();
        $pluginConfig = $options->plugin('BlogHelper');
        //北京时间
        date_default_timezone_set('PRC');
        // 数据库链接
        $db = Db::get();
        $prefix = $db->getPrefix();
        // 状态数据
        $status_data = $db->fetchRow($db->select()->from($prefix.'blog_helper_status')->where('1 = ?', 1)->order('created', Db::SORT_DESC));
        // 微信数据
        $step_data = $db->fetchRow($db->select()->from($prefix.'blog_helper_wechat')->where('1 = ?', 1)->order('created', Db::SORT_DESC));
        
        if($step_data !== null){
            $step_time = strtotime($step_data['created'] ?? '1970-01-01');
            $result['step_num'] = $step_data['steps'] ?? '';
            $result['step_short_date'] = date('Y-m-d', $step_time);
            $result['step_full_date'] = date('Y-m-d H:i:s', $step_time);
        }
        if($status_data !== null){
            $emojiId = $status_data['emojiId'];
            $emojiName = $status_data['emojiName'];
            $customText = $status_data['customText'];
            $text = !empty($customText) ? $customText : $emojiName;
            $status_time = strtotime($status_data['created'] ?? '1970-01-01');
            $current_time = time();
            $showTime = !empty($pluginConfig->showTime) ? $pluginConfig->showTime : 6;
            $mill = $showTime * 60 * 60;
            
            // 判断是否超过小时（$mill * 60 * 60 = XXX秒）
            if (($current_time - $status_time) <= $mill) {
                $result['status_text'] = $text;
                $result['status_pic_url'] = Plugin::iconUrl($emojiId);
                $result['status_short_date'] = date('Y-m-d', $status_time);
                $result['status_full_date'] = date('Y-m-d H:i:s', $status_time);
            }
        }
        
        
        
       return $result;
    }
    
    
    public static function renderStatus()
    {
        // 获取系统配置
        $options = Helper::options();
        $pluginConfig = $options->plugin('BlogHelper');
        // 获取管理员头像信息
        $user = Widget::widget('Widget_Users_Admin@uid=' . '1');
        $Plugin_Url = $options->pluginUrl .'/BlogHelper/';
        
        $db = Db::get();
        $prefix = $db->getPrefix();
        $status_data = $db->fetchRow($db->select()->from($prefix.'blog_helper_status')->where('1 = ?', 1)->order('created', Db::SORT_DESC));
        
        $showMyStatus = $pluginConfig->showMyStatus;
        
        if($showMyStatus === '1'){
            if($status_data === null){
                echo '';
            } else {
                 //北京时间
                date_default_timezone_set('PRC');
                // 获取时间戳
                $created_time = strtotime($status_data['created'] ?? '1970-01-01');
                $current_time = time();
                $showTime = !empty($pluginConfig->showTime) ? $pluginConfig->showTime : 6;
                $mill = $showTime * 60 * 60;
                
                // 判断是否超过小时（$mill * 60 * 60 = XXX秒）
                if (($current_time - $created_time) > $mill) {
                    echo '';
                } else {
                    $emojiId = $status_data['emojiId'];
                    $emojiName = $status_data['emojiName'];
                    $customText = $status_data['customText'];
                    $text = !empty($customText) ? $customText : $emojiName;
                    $iconUrl = Plugin::iconUrl($emojiId);
                    
                    echo '<link rel="stylesheet" href="'.$Plugin_Url.'assets/css/status.css" />';
                    echo '<div id="chrison-blog-helper-status" class="chrison-blog-helper-status">';
                    echo '<div class="chrison-blog-helper-status-gravatar">';
                    echo $user->gravatar('96', '');
                    echo '</div>';
                    echo '<div class="chrison-blog-helper-status-content">';
                    echo '<img src="'.$iconUrl.'" alt="'.$text.'" />';
                    echo '<span>'.'正在'.$text.'</span>';
                    echo '</div>';
                    echo '</div>';
                }
            }
        }
    }
    
    
    public static function paramsCode()
    {
        $params_html = "<div class='chrison-blog-helper-full'>
  <div class='step_num'>{step_num}</div>
  <div class='step_date'>{step_date}</div>
  <div class='my_status'>
    <div class='status_pic_wrapper'>
      <img class='status_pic' src='{status_pic_url}' width='32' height='32'>
    </div>
    <span class='status_text'>正在{status_text}</span>
  </div>
</div>";
        return $params_html;
    }
    
    public static function cssCode(){
        $css_code = ".chrison-blog-helper-full { 
  text-align: center; 
  padding: 20px; 
  margin: 0 auto; 
  color: #666; 
} 

.chrison-blog-helper-full .step_num {
  font-size: 24px; 
  font-weight: bold; 
  color: #4CAF50;
} 

.chrison-blog-helper-full .step_date {
  font-size: 12px; 
  color: #999;
}

.chrison-blog-helper-full .my_status {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 5px 0;
  gap: 8px;
  font-size: 12px; 
  color: #999;
}

.chrison-blog-helper-full .status_pic_wrapper {
  width: 32px;
  height: 32px;
  background-color: #4CAF50;  /* 绿色底色，可以改成你喜欢的颜色 */
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* 图片样式 */
.chrison-blog-helper-full .status_pic {
  filter: brightness(0) invert(1);
  display: block;
}

.chrison-blog-helper-full .status_text {
  
}";
        return $css_code;
    }
    
    /**
     * 状态图标
     *
     * @access public
     * @param unknown
     * @return void
     */
    public static function iconUrl($emojiId)
    {

        $Settings = Helper::options()->plugin('BlogHelper');
        $Plugin_Url = Helper::options()->pluginUrl .'/BlogHelper/';

        if ($emojiId == 'xqxf-mzz') { $iconUrl = $Plugin_Url.'assets/status/mzz.png'; }
        else if ($emojiId == 'xqxf-lk') { $iconUrl = $Plugin_Url.'assets/status/lk.png'; }
        else if ($emojiId == 'xqxf-qjl') { $iconUrl = $Plugin_Url.'assets/status/qjl.png'; }
        else if ($emojiId == 'xqxf-dqt') { $iconUrl = $Plugin_Url.'assets/status/dqt.png'; }
        else if ($emojiId == 'xqxf-pb') { $iconUrl = $Plugin_Url.'assets/status/pb.png'; }
        else if ($emojiId == 'xqxf-fd') { $iconUrl = $Plugin_Url.'assets/status/fd.png'; }
        else if ($emojiId == 'xqxf-c') { $iconUrl = $Plugin_Url.'assets/status/c.png'; }
        else if ($emojiId == 'xqxf-emo') { $iconUrl = $Plugin_Url.'assets/status/emo.png'; }
        else if ($emojiId == 'xqxf-hslx') { $iconUrl = $Plugin_Url.'assets/status/hslx.png'; }
        else if ($emojiId == 'xqxf-yqmm') { $iconUrl = $Plugin_Url.'assets/status/yqmm.png'; }
        else if ($emojiId == 'xqxf-bot') { $iconUrl = $Plugin_Url.'assets/status/bot.png'; }

        else if ($emojiId == 'gzxx-bz') { $iconUrl = $Plugin_Url.'assets/status/bz.png'; }
        else if ($emojiId == 'gzxx-cmxx') { $iconUrl = $Plugin_Url.'assets/status/cmxx.png'; }
        else if ($emojiId == 'gzxx-m') { $iconUrl = $Plugin_Url.'assets/status/m.png'; }
        else if ($emojiId == 'gzxx-my') { $iconUrl = $Plugin_Url.'assets/status/my.png'; }
        else if ($emojiId == 'gzxx-cc') { $iconUrl = $Plugin_Url.'assets/status/cc.png'; }
        else if ($emojiId == 'gzxx-fbhj') { $iconUrl = $Plugin_Url.'assets/status/fbhj.png'; }
        else if ($emojiId == 'gzxx-wrms') { $iconUrl = $Plugin_Url.'assets/status/wrms.png'; }

        else if ($emojiId == 'hd-l') { $iconUrl = $Plugin_Url.'assets/status/l.png'; }
        else if ($emojiId == 'hd-dk') { $iconUrl = $Plugin_Url.'assets/status/dk.png'; }
        else if ($emojiId == 'hd-yd') { $iconUrl = $Plugin_Url.'assets/status/yd.png'; }
        else if ($emojiId == 'hd-hkf') { $iconUrl = $Plugin_Url.'assets/status/hkf.png'; }
        else if ($emojiId == 'hd-hnc') { $iconUrl = $Plugin_Url.'assets/status/hnc.png'; }
        else if ($emojiId == 'hd-gf') { $iconUrl = $Plugin_Url.'assets/status/gf.png'; }
        else if ($emojiId == 'hd-dw') { $iconUrl = $Plugin_Url.'assets/status/dw.png'; }
        else if ($emojiId == 'hd-zjsj') { $iconUrl = $Plugin_Url.'assets/status/zjsj.png'; }
        else if ($emojiId == 'hd-zp') { $iconUrl = $Plugin_Url.'assets/status/zp.png'; }

        else if ($emojiId == 'xx-bg') { $iconUrl = $Plugin_Url.'assets/status/bg.png'; }
        else if ($emojiId == 'xx-z') { $iconUrl = $Plugin_Url.'assets/status/z.png'; }
        else if ($emojiId == 'xx-sj') { $iconUrl = $Plugin_Url.'assets/status/sj.png'; }
        else if ($emojiId == 'xx-xm') { $iconUrl = $Plugin_Url.'assets/status/xm.png'; }
        else if ($emojiId == 'xx-lg') { $iconUrl = $Plugin_Url.'assets/status/lg.png'; }
        else if ($emojiId == 'xx-wyx') { $iconUrl = $Plugin_Url.'assets/status/wyx.png'; }
        else if ($emojiId == 'xx-tg') { $iconUrl = $Plugin_Url.'assets/status/tg.png'; }

        else if ($emojiId == 'diy') { $iconUrl = ''; } //自选状态

        return $iconUrl;

    }
}
