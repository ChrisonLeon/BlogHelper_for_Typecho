<?php
use Typecho\widget;
use Widget\Options;
use Typecho\Db;
use Typecho\Common;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 处理 API 请求的动作类
 */
class BlogHelper_Action extends Typecho_Widget implements Widget_Interface_Do
{
    
    public function action(){
        
    }
    
    public function execute() {
        // 主入库
    }
    
        /**
     * 获取 JSON 输入数据
     * @return array
     */
    private function getJsonInput() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->response->setStatus(400);
            $this->response->throwJson([
                'status' => 'fail', 
                'message' => '无效的 JSON 数据：' . json_last_error_msg()
            ]);
            return null;
        }
        
        return $data ?: [];
    }
    
    public function api(){
        $request = $this->request;
        
        // 获取 JSON 输入数据
        $inputData = $this->getJsonInput();
        if ($inputData === null) {
            return; // getJsonInput 已经返回错误响应
        }
        
        //$action = $request->get('action','');
        $action = isset($inputData['action']) ? $inputData['action'] : '';
        
        if(empty($action)){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '参数异常']);
            return;
        }
        
        switch ($action) {
            case 'steps':
                $this->steps($inputData);
                break;
            case 'storys':
                $this->storys($inputData);
                break;
            default:
                $this->response->setStatus(403);
                $this->response->throwJson(['status' => 'fail', 'message' => '参数错误']);
                break;
        }
    }
    
    
    
    private function steps($inputData){
        // 从 JSON 数据中获取参数
        $action = isset($inputData['action']) ? $inputData['action'] : '';
        $openid = isset($inputData['openid']) ? $inputData['openid'] : '';
        $steps = isset($inputData['steps']) ? $inputData['steps'] : '';
        $timestamp = isset($inputData['timestamp']) ? $inputData['timestamp'] : '';
        $sign = isset($inputData['sign']) ? $inputData['sign'] : '';
        
        if(empty($openid)){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '参数异常（1）']);
        }
        
        if(empty($steps)){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '参数异常（2）']);
        }
        
        if(empty($sign)){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '参数异常（3）']);
        }
        
        // 获取系统配置选项
        $options = Options::alloc();
        // 获取插件配置
        $plugin = $options->plugin('BlogHelper');
        // 插件参数值
        $secret = $plugin->secret_key;
        
        // 加解密
        $_sign = md5($action . $openid . $steps . $secret);
        
        if($_sign !== $sign){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '校验失败']);
        }
        
        // 格式化时间
        $syncTime = date('Y-m-d H:i:s', is_numeric($timestamp) ? $timestamp : time());
        
        
        $db = Db::get();
        $prefix = $db->getPrefix();
        $row_id = $db->query($db->insert($prefix . 'blog_helper_wechat')
        ->rows([
            'openid' => $openid,
            'steps' => $steps,
            'created' => $syncTime
        ]));
        

        if ($row_id) {
            $this->response->setStatus(200);
            $this->response->throwJson(['status' => 'success', 'message' => '微信运动步数同步成功']);
        } else {
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '同步失败']);
        }
    }
    
    private function storys($inputData){
        $action = isset($inputData['action']) ? $inputData['action'] : '';
        $openid = isset($inputData['openid']) ? $inputData['openid'] : '';
        $title = isset($inputData['title']) ? $inputData['title'] : '';
        $content = isset($inputData['content']) ? $inputData['content'] : '';
        $tags = isset($inputData['tags']) ? $inputData['tags'] : '';
        $image = isset($inputData['image']) ? $inputData['image'] : '';
        $images = isset($inputData['images']) ? $inputData['images'] : '';
        $imagePosition = isset($inputData['imagePosition']) ? $inputData['imagePosition'] : '';
        $imageLayout = isset($inputData['imageLayout']) ? $inputData['imageLayout'] : '';
        $location = isset($inputData['location']) ? $inputData['location'] : [];
        $timestamp = isset($inputData['timestamp']) ? $inputData['timestamp'] : '';
        $sign = isset($inputData['sign']) ? $inputData['sign'] : '';
        
        if(empty($openid)){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '参数异常（1）']);
        }
        
        if(empty($title)){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '参数异常（2）']);
        }
        
        if(empty($sign)){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '参数异常（3）']);
        }
        
        // 链接数据库
        $db = Db::get();
        $prefix = $db->getPrefix();
        // 获取系统配置选项
        $options = Options::alloc();
        // 获取插件配置
        $plugin = $options->plugin('BlogHelper');
        // 插件参数值
        $secret = $plugin->secret_key;
        $mid = $plugin->mid;
        // markdown前缀
        $markdown = '<!--markdown-->';
        
        // 加解密
        $_sign = md5($action . $openid . $title . $secret);
        
        if($_sign !== $sign){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '校验失败']);
        }
        
        if(empty($mid) || $mid === '0'){
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '分类ID未配置']);
        }

        // 1.1. 图片下载
        $localImage = '';
        if (!empty($image)) {
            $localImage = $this->downloadImage($image);
            if (!$localImage) {
                $this->response->setStatus(403);
                $this->response->throwJson(['status' => 'fail', 'message' => '图片提交失败1']);
            }
        }
        // 1.2. 图片组下载
        $localImageList = [];
        if (!empty($images)) {
            $localImageList = $this->downloadImageList($images);
            if(empty($localImageList)){
                $this->response->setStatus(403);
                $this->response->throwJson(['status' => 'fail', 'message' => '图片提交失败2'.$localImageList]);
            }
        }
        
        // 2. 准备文章数据
        $slug = Typecho_Common::slugName($title);
        
        // 3. 确保slug唯一
        $existing = $db->fetchRow($db->select('cid')
            ->from('table.contents')
            ->where('slug = ?', $slug)
            ->where('type = ?', 'post'));
        
        if ($existing) {
            $slug .= '-' . time();
        }
        
        $postData = [
            'title'      => $title,
            'slug'       => $slug,
            'created'    => time(),
            'modified'   => time(),
            'text'       => $content,
            'authorId'   => 1,
            'type'       => 'post',
            'status'     => 'publish',
            'commentsNum' => 0,
            'allowComment' => 1,
            'allowPing'    => 1,
            'allowFeed'    => 1,
            'parent'     => 0,
            'views'      => 1,
            'password'   => null,
            'template'   => ''
        ];
        
        try {
            // 4. 插入文章
            $insert = $db->insert('table.contents')->rows($postData);
            $postId = $db->query($insert);
            
            if (!$postId) {
                $this->response->setStatus(403);
                $this->response->throwJson(['status' => 'fail', 'message' => '文章插入失败']);
            }
            
            // 5. 处理分类
            $relationshipData = [
                'cid' => $postId,
                'mid' => $mid
            ];
            
            $insertRelation = $db->insert('table.relationships')->rows($relationshipData);
            $db->query($insertRelation);
            
            // 6. 处理分类,需要count+1
            $mid_row = $db->fetchRow($db->select('count')->from('table.metas')->where('mid = ?', $mid));
			$db->query($db->update('table.metas')->rows(array('count' => (int) $mid_row['count'] + 1))->where('mid = ?', $mid));
            
            // 7. 处理标签,需要count+1
            if (!empty($tags)) {
                $this->processTags($postId, $tags);
            }

            // 8.1. 处理图片为附件
            $attachmentHtml = '';
            if (!empty($localImage)) {
                $attachmentHtml = $this->processImageAsAttachment($postId, $localImage);
            }
            //8.2. 处理图片组为附件（下面单图或多图做了兼容性拼接）
            if(!empty($localImageList)){
                $attachmentHtml = $attachmentHtml . $this->processImageAsAttachments($postId, $imageLayout, $localImageList);
            }
            
            // 9.拼接markdown、和图片html
            $finalContent = '';
            if (!empty($attachmentHtml)) {
                if ($imagePosition == 'top') {
                    $finalContent = $markdown . "\n\n" . $attachmentHtml . "\n\n" . $content;
                } elseif ($imagePosition == 'bottom') {
                    $finalContent = $markdown . "\n\n" . $content . "\n\n" . $attachmentHtml;
                }
            } else {
                $finalContent = $markdown . "\n\n" . $content;
            }
            
            // 10. 更新文章内容
            $db->query($db->update('table.contents')->rows(['text' => $finalContent])->where('cid = ?', $postId));
            
            // 11. 添加定位信息到自定义字段
            if(isset($location) && !empty($location)){
                $locationFields = [
                    'latitude' => 'chrison_location_latitude',
                    'longitude' => 'chrison_location_longitude', 
                    'name' => 'chrison_location_name',
                    'address' => 'chrison_location_address'
                ];
                
                $fieldsToSave = [];
                
                foreach ($locationFields as $sourceKey => $metaKey) {
                    if (!empty($location[$sourceKey])) {
                        $fieldsToSave[$metaKey] = $location[$sourceKey];
                    }
                }
                
                if (!empty($fieldsToSave)) {
                    $this->addPostCustomFields($postId, $fieldsToSave);
                }
            }
            
            // 12. 添加来源到自定义字段
            $this->addPostCustomFields($postId,['chrison_via' => '微信小程序']);
            
            $this->response->setStatus(200);
            $this->response->throwJson(['status' => 'success', 'message' => '发布成功']);
            
        } catch (Exception $e) {
            //$db->rollBack();
            // 记录错误日志
            error_log('插入文章失败：' . $e->getMessage());
            
            $this->response->setStatus(403);
            $this->response->throwJson(['status' => 'fail', 'message' => '发布失败：'.$e->getMessage()]);
        }
    }
    
    
    /**
     * 下载多个远程图片到本地
     * @param string $imageUrl 远程图片URL
     * @return string|false 返回本地图片路径或false
     */
    private function downloadImageList($imageListUrl)
    {
        if (empty($imageListUrl)) {
            return;
        }
        
        $localImageList = [];
        $allFailed = true;  // 标记是否全部失败
        
        try {
            // 获取上传目录配置
            $options = Helper::options();
            $uploadDir = $options->uploadDir ?: __TYPECHO_ROOT_DIR__ . '/usr/uploads';
            
            // 按年月创建目录
            $yearMonth = date('Ym');
            $day = date('d');
            $saveDir = $uploadDir . '/' . $yearMonth . '/' . $day . '/';
            
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0755, true);
            }
            
            foreach ($imageListUrl as $imageUrl) {
                // 生成文件名
                $ext = $this->getImageExtension($imageUrl);
                $fileName = uniqid() . '.' . $ext;
                $savePath = $saveDir . $fileName;
                
                // 相对路径（用于返回）
                $relativePath = '/usr/uploads/' . $yearMonth . '/' . $day . '/' . $fileName;
                
                // 多种下载方式，按顺序尝试
                $imageContent = false;
                
                // 方法1：使用 cURL（最可靠）
                if (function_exists('curl_init')) {
                    $imageContent = $this->downloadWithCurl($imageUrl);
                }
                
                // 方法2：使用 file_get_contents
                if (!$imageContent && ini_get('allow_url_fopen')) {
                    $imageContent = $this->downloadWithFileGetContents($imageUrl);
                }
                
                // 方法3：使用 Typecho_Http_Client
                if (!$imageContent && class_exists('Typecho_Http_Client')) {
                    $imageContent = $this->downloadWithTypechoClient($imageUrl);
                }
                
                // 方法4：使用 sockets
                if (!$imageContent) {
                    $imageContent = $this->downloadWithSockets($imageUrl);
                }
                
                if ($imageContent !== false) {
                    file_put_contents($savePath, $imageContent);
                    // 附加本地地址
                    $localImageList[] = $relativePath;
                    $allFailed = false;  // 至少有一个成功
                } else {
                    error_log('下载图片失败：' . $imageUrl);
                }
                
            }
            
            // 如果全部失败才返回 false
            if ($allFailed) {
                error_log('所有图片下载都失败');
                return false;
            }
        } catch (Exception $e) {
            error_log('下载图片失败：' . $e->getMessage() . ' URL: ' . $imageUrl);
        }
        
        return $localImageList;
    }
    
    /**
     * 下载远程图片到本地
     * @param string $imageUrl 远程图片URL
     * @return string|false 返回本地图片路径或false
     */
    private function downloadImage($imageUrl)
    {
        try {
            // 获取上传目录配置
            $options = Helper::options();
            $uploadDir = $options->uploadDir ?: __TYPECHO_ROOT_DIR__ . '/usr/uploads';
            
            // 按年月创建目录
            $yearMonth = date('Ym');
            $day = date('d');
            $saveDir = $uploadDir . '/' . $yearMonth . '/' . $day . '/';
            
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0755, true);
            }
            
            // 生成文件名
            $ext = $this->getImageExtension($imageUrl);
            $fileName = uniqid() . '.' . $ext;
            $savePath = $saveDir . $fileName;
            
            // 相对路径（用于返回）
            $relativePath = '/usr/uploads/' . $yearMonth . '/' . $day . '/' . $fileName;
            
            // 多种下载方式，按顺序尝试
            $imageContent = false;
            
            // 方法1：使用 cURL（最可靠）
            if (function_exists('curl_init')) {
                $imageContent = $this->downloadWithCurl($imageUrl);
            }
            
            // 方法2：使用 file_get_contents
            if (!$imageContent && ini_get('allow_url_fopen')) {
                $imageContent = $this->downloadWithFileGetContents($imageUrl);
            }
            
            // 方法3：使用 Typecho_Http_Client
            if (!$imageContent && class_exists('Typecho_Http_Client')) {
                $imageContent = $this->downloadWithTypechoClient($imageUrl);
            }
            
            // 方法4：使用 sockets
            if (!$imageContent) {
                $imageContent = $this->downloadWithSockets($imageUrl);
            }
            
            if ($imageContent !== false) {
                file_put_contents($savePath, $imageContent);
                
                // 验证是否真的是图片  php需要安装fileinfo扩展
                // $finfo = finfo_open(FILEINFO_MIME_TYPE);
                // $mimeType = finfo_file($finfo, $savePath);
                // finfo_close($finfo);
                
                // if (strpos($mimeType, 'image/') === 0) {
                //     // 返回相对路径用于存储
                //     $relativePath = '/' . $yearMonth . '/' . $day . '/' . $fileName;
                //     return $relativePath;
                // } else {
                //     // 不是图片，删除文件
                //     unlink($savePath);
                //     error_log('下载的文件不是图片：' . $mimeType);
                //     return false;
                // }
                
                // 临时改为直接返回（不验证）
                return $relativePath;
            }
            
            error_log('所有下载方法都失败：' . $imageUrl);
            return false;
            
        } catch (Exception $e) {
            error_log('下载图片失败：' . $e->getMessage() . ' URL: ' . $imageUrl);
            return false;
        }
    }
    
    
    private function processImageAsAttachments($postId, $imageLayout, $imageListUrl){
        
        $db = Db::get();
        
        // 根据布局确定容器类
        $containerClass = 'chrison_image_container';
        switch ($imageLayout) {
            case 'grid9':
                $containerClass .= ' chrison_grid_9';
                break;
            case 'grid4':
                $containerClass .= ' chrison_grid_4';
                break;
            case 'row':
                $containerClass .= ' chrison_row';
                break;
            case 'column':
                $containerClass .= ' chrison_column';
                break;
        }
        
        $html = '';
        $index = 0;
        $totalImages = count($imageListUrl);
        
        foreach ($imageListUrl as $imageUrl){
            // 获取文件的绝对路径
            $absolutePath = __TYPECHO_ROOT_DIR__ . $imageUrl;
            
            if (!file_exists($absolutePath)) {
                throw new Exception('文件不存在');
            }
            
            // 获取文件信息
            $fileSize = filesize($absolutePath);
            $fileInfo = pathinfo($imageUrl);
            
            // 构造元数据数组
            $attachment = [
                'name' => $fileInfo['basename'],    // 文件名
                'path' => $imageUrl,           // 相对路径
                'size' => $fileSize,                // 文件大小（字节）
                'type' => 'application/octet-stream', // MIME类型
                'mime' => Common::mimeContentType($absolutePath) // 更精确的MIME类型
            ];
            
            // 插入数据
            $data = [
                'title' => $attachment['name'],           // 标题，通常为文件名
                'slug' => $attachment['name'],            // 缩略名，也可生成唯一值
                'created' => time(),                      // 创建时间戳
                'modified' => time(),                     // 修改时间戳
                'text' => serialize($attachment),         // 元数据序列化
                'order' => 0,                             // 排序
                'authorId' => 1,                    // 作者ID
                'template' => '',                         // 模板
                'type' => 'attachment',                    // 类型必须是 'attachment'
                'status' => 'publish',                    // 状态
                'password' => '',                         // 密码
                'commentsNum' => 0,                       // 评论数
                'allowComment' => 1,                      // 允许评论
                'allowPing' => 1,                         // 允许ping
                'allowFeed' => 1,                         // 允许feed
                'parent' => $postId,                      // 关联到文章
                'views' => 0
            ];
            
            // 插入记录
            $insertId = $db->query($db->insert('table.contents')->rows($data));
            
            if (!$insertId) {
                throw new Exception('附件记录插入失败');
            }
            
            // 生成图片html代码
            //$html .= "\n\n".'<a class="chrison_image_a" href="'.$imageUrl.'">'. "\n\n";
            //$html .= '<img class="chrison_image_attachment" src="' . $imageUrl . '" alt="' . $attachment['name'] . '">';
            //$html .= "\n\n".'</a>'."\n\n";
            
            // 根据布局添加不同的包裹结构
            switch ($imageLayout) {
                case 'grid9':
                case 'grid4':
                    // 网格布局：每个图片用grid_item包裹
                    $html .= '<div class="chrison_grid_item">';
                    $html .= '<a class="chrison_image_a" href="'.$imageUrl.'">';
                    $html .= '<img class="chrison_image_attachment" src="' . $imageUrl . '" alt="' . $attachment['name'] . '">';
                    $html .= '</a>';
                    $html .= '</div>';
                    break;
                    
                case 'row':
                    // 一行布局：图片直接是row_item
                    $html .= '<a class="chrison_image_a chrison_row_item" href="'.$imageUrl.'">';
                    $html .= '<img class="chrison_image_attachment" src="' . $imageUrl . '" alt="' . $attachment['name'] . '">';
                    $html .= '</a>';
                    break;
                    
                case 'column':
                    // 一列布局：每个图片用column_item包裹
                    $html .= '<div class="chrison_column_item">';
                    $html .= '<a class="chrison_image_a" href="'.$imageUrl.'">';
                    $html .= '<img class="chrison_image_attachment" src="' . $imageUrl . '" alt="' . $attachment['name'] . '">';
                    $html .= '</a>';
                    $html .= '</div>';
                    break;
                    
                case 'none':
                default:
                    // 无样式：保持原样
                    $html .= '<a class="chrison_image_a" href="'.$imageUrl.'">';
                    $html .= '<img class="chrison_image_attachment" src="' . $imageUrl . '" alt="' . $attachment['name'] . '">';
                    $html .= '</a>';
                    break;
            }
            
            $index++;
        }
        
        // 网格布局补足占位符
        // if (($imageLayout == 'grid9' || $imageLayout == 'grid4') && $totalImages < 9) {
        //     $targetCount = ($imageLayout == 'grid9') ? 9 : 4;
        //     for ($i = $totalImages; $i < $targetCount; $i++) {
        //         $html .= '<div class="chrison_grid_item chrison_placeholder"></div>';
        //     }
        // }
        
        // 外层容器
        $html = '<div class="' . $containerClass . '">' . $html . '</div>';
        
        // 添加样式（只需要添加一次，所以放在循环外面）
        $html .= $this->getImageLayoutStyles($imageLayout);
        
        return $html;
    }
    
    private function processImageAsAttachment($postId, $imageUrl){
        $db = Db::get();
        
        // 获取文件的绝对路径
        $absolutePath = __TYPECHO_ROOT_DIR__ . $imageUrl;
        
        if (!file_exists($absolutePath)) {
            throw new Exception('文件不存在');
        }
        
        // 获取文件信息
        $fileSize = filesize($absolutePath);
        $fileInfo = pathinfo($imageUrl);
        
        // 构造元数据数组
        $attachment = [
            'name' => $fileInfo['basename'],    // 文件名
            'path' => $imageUrl,           // 相对路径
            'size' => $fileSize,                // 文件大小（字节）
            'type' => 'application/octet-stream', // MIME类型
            'mime' => Common::mimeContentType($absolutePath) // 更精确的MIME类型
        ];
        
        // 插入数据
        $data = [
            'title' => $attachment['name'],           // 标题，通常为文件名
            'slug' => $attachment['name'],            // 缩略名，也可生成唯一值
            'created' => time(),                      // 创建时间戳
            'modified' => time(),                     // 修改时间戳
            'text' => serialize($attachment),         // 元数据序列化
            'order' => 0,                             // 排序
            'authorId' => 1,                    // 作者ID
            'template' => '',                         // 模板
            'type' => 'attachment',                    // 类型必须是 'attachment'
            'status' => 'publish',                    // 状态
            'password' => '',                         // 密码
            'commentsNum' => 0,                       // 评论数
            'allowComment' => 1,                      // 允许评论
            'allowPing' => 1,                         // 允许ping
            'allowFeed' => 1,                         // 允许feed
            'parent' => $postId,                      // 关联到文章
            'views' => 0
        ];
        
        // 插入记录
        $insertId = $db->query($db->insert('table.contents')->rows($data));
        
        if (!$insertId) {
            throw new Exception('附件记录插入失败');
        }
        
        // 生成图片html代码
        $html = '<p>'. "\n\n";
        $html .= '<img class="chrison-attachment" src="' . $imageUrl . '" alt="' . $attachment['name'] . '">';
        $html .= "\n\n".'</p>';
        
       return $html;
    }
    
    /**
     * 使用 cURL 下载
     */
    private function downloadWithCurl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  // 跟随重定向
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);        // 最大重定向次数
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);         // 超时时间
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过SSL验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode == 200) ? $content : false;
    }
    
    /**
     * 使用 file_get_contents 下载
     */
    private function downloadWithFileGetContents($url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'follow_location' => 1,
                'max_redirects' => 5
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        return file_get_contents($url, false, $context);
    }
    
    /**
     * 使用 Typecho_Http_Client 下载
     */
    private function downloadWithTypechoClient($url)
    {
        try {
            $client = Typecho_Http_Client::get();
            if ($client) {
                $client->setMethod('GET')
                       ->setHeader('User-Agent', 'Mozilla/5.0')
                       ->setTimeout(30)
                       ->send($url);
                
                if ($client->getResponseStatus() == 200) {
                    return $client->getResponseBody();
                }
            }
        } catch (Exception $e) {
            error_log('Typecho_Http_Client 下载失败：' . $e->getMessage());
        }
        return false;
    }
    
    /**
     * 使用 sockets 下载（最后的备选方案）
     */
    private function downloadWithSockets($url)
    {
        $parts = parse_url($url);
        $host = $parts['host'];
        $path = isset($parts['path']) ? $parts['path'] : '/';
        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        
        $port = isset($parts['port']) ? $parts['port'] : 80;
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'http';
        
        if ($scheme == 'https') {
            $host = 'ssl://' . $host;
            $port = 443;
        }
        
        $fp = @fsockopen($host, $port, $errno, $errstr, 30);
        if (!$fp) {
            return false;
        }
        
        $out = "GET $path HTTP/1.1\r\n";
        $out .= "Host: {$parts['host']}\r\n";
        $out .= "User-Agent: Mozilla/5.0\r\n";
        $out .= "Connection: Close\r\n\r\n";
        
        fwrite($fp, $out);
        
        $content = '';
        $header = true;
        while (!feof($fp)) {
            $line = fgets($fp, 1024);
            if ($header && $line == "\r\n") {
                $header = false;
                continue;
            }
            if (!$header) {
                $content .= $line;
            }
        }
        fclose($fp);
        
        return $content;
    }
    
    /**
     * 获取图片扩展名
     */
    private function getImageExtension($url)
    {
        // 从URL中获取扩展名
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!empty($ext) && in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
            return $ext;
        }
        
        // 尝试从URL内容类型判断
        $headers = @get_headers($url, 1);
        if ($headers && isset($headers['Content-Type'])) {
            $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
            switch ($contentType) {
                case 'image/jpeg':
                    return 'jpg';
                case 'image/png':
                    return 'png';
                case 'image/gif':
                    return 'gif';
                case 'image/webp':
                    return 'webp';
                case 'image/bmp':
                    return 'bmp';
            }
        }
        
        return 'jpg'; // 默认扩展名
    }
    
    
    /**
     * 处理标签
     * @param int $postId 文章ID
     * @param string $tags 标签
     */
    private function processTags($postId, $tags)
    {
        if (empty($tags)) {
            return;
        }
        
        $db = Db::get();
        // 处理标签：如果已经是数组，直接使用；如果是字符串，按逗号分割
        if (is_array($tags)) {
            $tagNames = $tags;
        } else {
            // 替换中文逗号为英文逗号，然后分割
            $tagNames = explode(',', str_replace('，', ',', $tags));
        }
        
        foreach ($tagNames as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }
            
            // 检查标签是否存在
            $slug = Common::slugName($tagName);
            $tag = $db->fetchRow($db->select('mid')
                ->from('table.metas')
                ->where('slug = ?', $slug)
                ->where('type = ?', 'tag'));
            
            if ($tag) {
                $tagId = $tag['mid'];
                
                // 更新标签计数
                $db->query($db->update('table.metas')->rows(array('count' => (int) $tag['count'] + 1))->where('mid = ?', $tagId));
                    
            } else {
                // 创建新标签
                $tagData = [
                    'name' => $tagName,
                    'slug' => $slug,
                    'type' => 'tag',
                    'description' => '',
                    'count' => 1,
                    'order' => 0,
                    'parent' => 0
                ];
                
                $insert = $db->insert('table.metas')->rows($tagData);
                $tagId = $db->query($insert);
            }
            
            // 插入标签关系
            $relationData = [
                'cid' => $postId,
                'mid' => $tagId
            ];
            
            // 检查关系是否已存在
            $exists = $db->fetchRow($db->select('*')
                ->from('table.relationships')
                ->where('cid = ?', $postId)
                ->where('mid = ?', $tagId));
                
            if (!$exists) {
                $insertRelation = $db->insert('table.relationships')->rows($relationData);
                $db->query($insertRelation);
            }
        }
    }
    
    
    /**
     * 为文章添加自定义字段
     * @param int $cid 文章CID
     * @param array $fields 字段数组，如 ['custom_field1' => 'value1', 'custom_field2' => 'value2']
     * @return bool
     */
    function addPostCustomFields($cid, $fields)
    {
        if (empty($cid) || empty($fields) || !is_array($fields)) {
            return false;
        }
        
        $db = Db::get();
        
        try {
            foreach ($fields as $name => $value) {
                // 检查字段是否已存在
                $exists = $db->fetchRow($db->select()
                    ->from('table.fields')
                    ->where('cid = ?', $cid)
                    ->where('name = ?', $name));
                
                if ($exists) {
                    // 更新现有字段
                    $db->query($db->update('table.fields')
                        ->rows(['str_value' => $value])
                        ->where('cid = ?', $cid)
                        ->where('name = ?', $name));
                } else {
                    // 插入新字段
                    $db->query($db->insert('table.fields')
                        ->rows([
                            'cid' => $cid,
                            'name' => $name,
                            'type' => 'str',
                            'str_value' => $value,
                            'int_value' => 0,
                            'float_value' => 0.0
                        ]));
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("添加自定义字段失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取图片布局的CSS样式
     * @param string $imageLayout
     * @return string
     */
    private function getImageLayoutStyles($imageLayout)
    {
        $styles = '';
        
        switch ($imageLayout) {
            case 'grid9':
                $styles = '
    !!!
    <style>
    .chrison_grid_9 {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 10px !important;
        margin: 15px 0 !important;
    }
    .chrison_grid_9 .chrison_grid_item {
        position: relative !important;
        width: 100% !important;
        padding-bottom: 100% !important; /* 1:1 宽高比 */
        overflow: hidden !important;
        border-radius: 8px !important;
        background: #f5f5f5 !important;
    }
    .chrison_grid_9 .chrison_grid_item a {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        display: block !important;
    }
    .chrison_grid_9 .chrison_grid_item img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        transition: transform 0.3s ease !important;
    }
    .chrison_grid_9 .chrison_grid_item img:hover {
        transform: scale(1.05) !important;
    }
    .chrison_grid_9 .chrison_placeholder {
        background: #f0f0f0 !important;
        border: 2px dashed #ddd !important;
    }
    @media (max-width: 768px) {
        .chrison_grid_9 {
            gap: 5px !important;
        }
    }
    </style>
    !!!';
                break;
                
            case 'grid4':
                $styles = '
    !!!
    <style>
    .chrison_grid_4 {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 15px !important;
        margin: 15px 0 !important;
    }
    .chrison_grid_4 .chrison_grid_item {
        position: relative !important;
        width: 100% !important;
        padding-bottom: 75% !important; /* 4:3 宽高比 */
        overflow: hidden !important;
        border-radius: 10px !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
        background: #f5f5f5 !important;
    }
    .chrison_grid_4 .chrison_grid_item a {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        display: block !important;
    }
    .chrison_grid_4 .chrison_grid_item img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }
    .chrison_grid_4 .chrison_placeholder {
        background: #f9f9f9 !important;
        border: 2px dashed #ccc !important;
        border-radius: 10px !important;
    }
    @media (max-width: 480px) {
        .chrison_grid_4 {
            gap: 8px !important;
        }
    }
    </style>
    !!!';
                break;
                
            case 'row':
                $styles = '
    !!!
    <style>
    .chrison_row {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;  /* 关键：禁止换行 */
        overflow-x: auto !important;   /* 允许水平滚动 */
        gap: 15px !important;
        margin: 15px 0 !important;
        padding: 5px 0 !important;     /* 给滚动条留点空间 */
        -webkit-overflow-scrolling: touch !important; /* 平滑滚动 */
    }
    .chrison_row .chrison_row_item {
        flex: 0 0 auto !important;
        width: 200px !important;
        height: 150px !important;
        overflow: hidden !important;
        border-radius: 8px !important;
        scroll-snap-align: start !important; /* 可选：滚动对齐 */
    }
    .chrison_row .chrison_row_item:hover {
        transform: translateY(-5px) !important;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2) !important;
    }
    .chrison_row .chrison_row_item img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }
    /* 隐藏滚动条（可选） */
    .chrison_row::-webkit-scrollbar {
        display: none;  /* Chrome/Safari */
    }
    .chrison_row {
        -ms-overflow-style: none;  /* IE/Edge */
        scrollbar-width: none;     /* Firefox */
    }
    @media (max-width: 768px) {
        .chrison_row {
            gap: 10px !important;
        }
        .chrison_row .chrison_row_item {
            width: 150px !important;
            height: 112px !important;
        }
    }
    @media (max-width: 480px) {
        .chrison_row {
            justify-content: center !important;
        }
        .chrison_row .chrison_row_item {
            width: 130px !important;
            height: 97px !important;
        }
    }
    </style>
    !!!';
                break;
                
            case 'column':
                $styles = '
    !!!
    <style>
    .chrison_column {
        display: flex !important;
        flex-direction: column !important;
        gap: 20px !important;
        margin: 15px 0 !important;
    }
    .chrison_column .chrison_column_item {
        width: 100% !important;
        max-width: 600px !important;
        margin: 0 auto !important;
        border-radius: 12px !important;
        overflow: hidden !important;
        box-shadow: 0 3px 10px rgba(0,0,0,0.15) !important;
        background: #fff !important;
    }
    .chrison_column .chrison_column_item a {
        display: block !important;
        width: 100% !important;
    }
    .chrison_column .chrison_column_item img {
        width: 100% !important;
        height: auto !important;
        display: block !important;
        transition: opacity 0.3s ease !important;
    }
    .chrison_column .chrison_column_item img:hover {
        opacity: 0.9 !important;
    }
    @media (max-width: 768px) {
        .chrison_column .chrison_column_item {
            max-width: 100% !important;
            border-radius: 8px !important;
        }
    }
    </style>
    !!!';
                break;
                
            default:
                $styles = '
    !!!
    <style>
    .chrison_image_container {
        margin: 15px 0 !important;
    }
    .chrison_image_container .chrison_image_a {
        display: inline-block !important;
        margin: 5px !important;
        border-radius: 4px !important;
        overflow: hidden !important;
    }
    .chrison_image_container img {
        max-width: 100% !important;
        height: auto !important;
        border-radius: 4px !important;
    }
    </style>
    !!!';
                break;
        }

        return $styles;
    }
}