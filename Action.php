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
        $imagePosition = isset($inputData['imagePosition']) ? $inputData['imagePosition'] : '';
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

        // 1. 图片下载
        $localImage = '';
        if (!empty($image)) {
            $localImage = $this->downloadImage($image);
            if (!$localImage) {
                $this->response->setStatus(403);
                $this->response->throwJson(['status' => 'fail', 'message' => '图片提交失败']);
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

            // 8. 处理图片为附件
            $attachmentHtml = '';
            if (!empty($localImage)) {
                $attachmentHtml = $this->processImageAsAttachment($postId, $localImage);
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
            
            // 10.更新文章内容
            $db->query($db->update('table.contents')->rows(['text' => $finalContent])->where('cid = ?', $postId));
            
            // 11.添加自定义字段
            $this->addPostCustomFields($postId,['chrison_via' => '微信小程序', 'chrison_version' => '1.0.0']);
            
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
    
    private function processImageAsAttachment($postId, $imageUrl){
        // 获取文件的绝对路径
        $absolutePath = __TYPECHO_ROOT_DIR__ . $imageUrl;
        
        if (!file_exists($absolutePath)) {
            throw new Exception('文件不存在');
        }
        
        
        $db = Db::get();
        
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
}