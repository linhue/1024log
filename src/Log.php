<?php
namespace Logservice;
use Logservice\DirService;
use think\Controller;
class Log  extends Controller
{

    private $dirPath = '/runtime/log/';
    private $dataDirPath = '/runtime/data/';
    private $show_ = ['request_uri'=>'请求路由','request_type'=>'请求方式','created_at'=>'请求时间', 'request_ip'=>'访问ip', 'system_run_info'=>'运行状况', 'PARAM'=>'请求参数', 'DB'=>"数据库访问", 'SQ'=>'sql信息','ROUTE'=>'路由详情','RUN'=>'运行文件','ERROR'=>'错误信息'];

    public function index()
    {
        $this->root_path = trim($_SERVER['DOCUMENT_ROOT'],'/public');
        //检查data是否存在
        $this->mkdirs($this->root_path.$this->dataDirPath);
        
        $this->assign('server_name',$_SERVER['SERVER_NAME']?:'');
        //组合uri
        $param  =  request()->param();
        $param['year'] = isset($param['year'])?$param['year']:date('Ym');
        $param['month'] = isset($param['month'])?$param['month']:date('d').'.log';
        $uri = $param['year'].'/'.$param['month'];
        //右侧显示
        $this->assign('data', $data =$this->get_sys_data($uri));
        $this->assign('show_',$this->show_);
        $this->assign('this_', (new Log()));

        //左侧显示 栏目
        $dirPath = !isset($_SESSION['dirPath'])?$this->dirPath:$_SESSION['dirPath'];
        $arr = DirService::each_dir($this->root_path.$dirPath);
        $this->assign('year_',$param['year']);
        $this->assign('month_',$param['month']);
        $this->assign('arr', $arr);
        return $this->fetch($this->root_path."/vendor/1024log/logserver/src/view/index.html");
    }
    private function mkdirs($dir, $mode = 0777)
    {
        if (is_dir($dir) || @mkdir($dir, $mode)) return TRUE;
        if (!mkdirs(dirname($dir), $mode)) return FALSE;
        return @mkdir($dir, $mode);
    }
    //处理请求参数
    public function str_($arr)
    {
        $data = [];
        foreach($arr as $key=>$v)
        {
            $data[] = ['key'=>$key,'val'=>$v];
        }
        return json_encode(($data), JSON_UNESCAPED_UNICODE);
    }
    public function getHtml()
    {
        $param = request()->get();

        return '';
    }
    //存树状数据
    private $three_data_= [];
    private function get_sys_data($uri)
    {

        $page = (isset($param['page'])  && !empty($param['page']) )?$param['page']:1;
        $limit = (isset($param['limit']) && !empty($param['limit']) )?$param['limit']:10;
        $res = '';

        if(!file_exists($this->root_path.$this->dirPath.$uri))
        {
            return [
                'page'=>1,
                'limit'=>$limit,
                'count' => 0,
                'page_num' => 0,
                'infos'=>[],
            ];
        }

        if(!file_exists($this->dataDirPath.(md5($uri).'.php')))
        {
           $res =  $this->make_three_file($this->root_path.$this->dirPath.$uri, (md5($uri)));
        }
        

        $temp_length = 0;
        $temp_key = 0;
        $param = request()->get();


        $length = $limit;
        if($page>1)
        {
            $length = ($page-1)*$limit;
        }else{
            $length = 0;
        }
        //查询文件是否存在
        $data = DirService::get_file_array($this->root_path.$this->dataDirPath.(md5($uri)).'.php');
        $temp_data = [];
        foreach($data as $key=>$v)
        {
           $temp_data = array_merge_recursive($temp_data, $v['infos']);
        }
        $arr =   array_slice($temp_data,$length,($limit),true);
        $count = count($temp_data);
        return [
            'page'=>$page,
            'limit'=>$limit,
            'count' => $count,
            'page_num' => ceil($count/$limit),
            'infos'=>array_values($arr),
        ];
       
    }


    public function getFileStr($field,$arr){
        if(!isset($arr[$field]))
        {
            return null;
        }

        if(is_array($arr[$field]))
        {
            return (string)json_encode($arr[$field],JSON_UNESCAPED_UNICODE);
        }
        return (string)$arr[$field];
    }    
    // /**
    //  * 列表head tr数据
    //  * 
    //  * @author 林虎
    //  * @detail conf
    //  * @return bool 
    //  */
    // public function conf()
    // {
    //     $arr = [
    //         [
    //             'name'=>'请求数据',
    //         ]
    //     ];

    // }

    /**
     * 生成索引文件
     * 
     * @author 林虎
     * @detail make file
     * @return bool 
     */
    private function make_three_file($file_path, $temp_)
    {
        if(!file_exists($file_path))
        {
            return false;
        }
        $fp = fopen($file_path,"r");
        $str = fread($fp,filesize($file_path));//指定读取大小，这里把整个文件内容读取出来
         $str = str_replace("\r\n","<br />",$str);
         $str = str_replace("<br />",' ',$str);
        fclose($fp);
        $arr = explode('---------------------------------------------------------------',$str);
        foreach($arr as $key =>&$val)
        {
            if(empty($val)) continue;


            if(strstr($val,'[ sql ]')){
                $val = str_replace('[ sql ]','[ info ]',$val);
            };
            if(strstr($val,'[ error ]')){
                $val = str_replace('[ error ]','[ info ]',$val);
                if(strstr($val,'[0]'))
                {

                    $val = str_replace('[0]','[ ERROR ]',$val);
                }
            };
            $val = str_replace('[ sql ]','[ info ]',$val);
            //分割词条字符串
            $temp_two_arr = explode("[ info ]",$val);

            //筛选出 年月日
            preg_match("/\d{4}-\d{2}-\d{2}/", $val, $year_key);
            $year_key = (is_array($year_key)?  array_pop($year_key): date('Y-m-d'));

            //筛选出 年月日时
            preg_match("/\d{4}-\d{2}-\d{2}T\d{2}:/", $val, $time);
            @$time = str_replace("T", " ", $time);
            $time_key = (is_array($time)? array_pop($time):'');
            $temp_data = [];
            foreach($temp_two_arr as $k=>$v)
            {
                if(true ==  preg_match("/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/", $v, $res)  && $this->hide($v))
                { 

                    //时间获取 
                    @$date_ =  str_replace("T", " ", $res);
                   
                    // ['created_at'][] =  (is_array($date_)? array_pop($date_):'');
                    @$temp_data['created_at'] =  (is_array($date_)? array_pop($date_):'');
                   
                    //获取请求方式 
                    @$temp_data['request_type']  = $this->getmonthed($v);
                    //匹配出Ip
                    @preg_match("/(?:(?:25[0-9]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])\.){3}(?:25[0-9]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])/", $v, $ip);
                    @$temp_data['request_ip']= (is_array($ip)?  array_pop($ip):'');
                   
                    //匹配出访问的路由地址
                    @preg_match('/(.com|.cn).*\[运行/' ,$v ,$uri);
                    @$temp_data['request_uri'] = (is_array($uri) and count($uri)==2 and $right = array_pop($uri) and $left = array_pop($uri))?  trim(trim($left, $right), '[运行'):'';
                   
                    //程序运行 消耗资源信息
                    @preg_match('/\[运行时间.*\]/' ,$v ,$sys_info1);
                    @$temp_data['system_run_info'] = is_array($sys_info1)? array_pop($sys_info1):'';
                }else{
                    //匹配 [DB] [ HEADER ] [ PARAM ].....
                    if(preg_match('/\[.*[A-Z]{3,6} \]/',$v,$a) && (@$like_str =  (is_array($a)?array_pop($a):false)) && true == strstr($v,$like_str) && $this->hide($v))
                    {
                     
                        $like_str_ = trim($like_str, '[ ');
                        $like_str_ = trim($like_str_, ' ]');
                        // $like_str = trim($like_str, ' ');
                        
                        // if(strstr($v,'array'))
                        // {
                            // @$temp_data[$like_str_][] = (array)$this->str_to_array(trim($v, $like_str));
                            // if(!is_array((array)trim($v, $like_str)))
                            // {
                            //     @$data[$year_key][$like_str_][] = $this->str_to_array(trim($v, $like_str));
                            //     @$temp_data[$like_str_][] =  $this->str_to_array(trim($v, $like_str));
                            // }else{
                            //     foreach( ((array)trim($v, $like_str)) as $kes=>$vs )
                            //     {
                            //         if(strstr($vs,'array'))
                            //         {   

                            //             if($like_str == '[ PARAM ]')
                            //             {
                            //                 @$temp_data[$like_str_][] = $this->str_to_array($vs,'json');

                            //             }else{
                            //                 @$temp_data[$like_str_][] = $this->str_to_array($vs);
                            //             }
                            //         }else{
                            //             @$temp_data[$like_str_][] = (string)$vs;
                            //         }
                            //     }
                            // }
                        // }else{
                            if(in_array($like_str_, ['HEADER','PARAM']))
                            {
                                if(in_array($like_str_,['PARAM']) and strstr($v,'array'))
                                {
                                    @$temp_data[$like_str_]  = $this->str_to_array((string)trim($v, $like_str));
                                }else{
                                    @$temp_data[$like_str_]  = (string)trim($v, $like_str);
                                }
                            }else{
                                    @$temp_data[$like_str_][]  = (string)trim($v, $like_str);
                            }
                        // }
                    }
                }

                @$temp_data['key_word'] = 'test_data';

            }
            @$data[$time_key]['infos'][] = $temp_data;
            @$data[$time_key]['key_word'][] = 'test_data';
            //存储日月年的 key word
        }
        DirService::arr_insert_file($this->root_path.$this->dataDirPath.$temp_.'.php',$data);
        return true;
    }
    /**
     * 请求方式
     * 
     * @author 林虎
     * @detail hide
     * @param str 需要判断的字符串
     * @return string 
     */
    private function getmonthed($str = 'GET')
    {
        if(strstr($str,'GET') or strstr($str,'get'))
        {
            return 'GET';
        }
        if(strstr($str,'POST') or strstr($str,'post'))
        {
            return 'POST';
        }
        if(strstr($str,'PUT') or strstr($str,'put'))
        {
            return 'PUT';
        }
        if(strstr($str,'PATCH') or strstr($str,'patch'))
        {
            return 'PATCH';
        }
        if(strstr($str,'DELETE') or strstr($str,'delete'))
        {
            return 'DELETE';
        }
        return 'GET';
    }
    /**
     * 隐藏敏感信息
     * 
     * @author 林虎
     * @detail hide
     * @param str 需要处理的字符串
     * @return bool 
     */
    private function hide($str = '')
    {
        $str = strtolower($str);
        if(strstr($str,'mysql:host'))
        {
            return false;
        }
        if(strstr($str,'password'))
        {
            return false;
        }
        return true;
    }
    /**
     * 隐藏敏感信息
     * 
     * @author 林虎
     * @detail hide
     * @param str 需要处理的字符串
     * @param type array  json
     * @return array/json 
     */
    private function str_to_array($str='', $type='array')
    {
        $str = str_replace("true", "'1'", $str);
        $str = str_replace("false", "'0'", $str);

        $str = str_replace("\n",' ',$str);
        $str = str_replace("<br />",' ',$str);
        preg_match("/'.*'/",$str,$val);
        $val = str_replace("=>", ':', is_array($val)?array_pop($val):'');

        $val = str_replace("array (", '[', is_array($val)?array_pop($val):$val);
        $val = str_replace(",   )", ']', is_array($val)?array_pop($val):$val);

        preg_match_all("/\d : /",$val,$arr);
        foreach($arr as $key =>$v)
        {
            $val = str_replace($v, ' ', $val);
        }
        $val =  str_replace("'", '"', $val);
        if($type == 'array')
        {
        return json_decode('{'.$val.'}',true);
        }
        return '{'.$val.'}';
    }
  



    
}