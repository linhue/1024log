<?php
namespace Logservice;

class DirService  
{
    /** 
     * 数组写入文件
     * 
     * @author 林虎
     * @detail arr_insert_file
     * @param file 文件路径
     * @param array 数组
     * @return bool 
     */
    public  static function arr_insert_file($file, $array){
        if(empty($array)) return false;
        if(empty($file)) return false;

        if(fopen($file, 'w'))
        {
            if(file_put_contents($file, json_encode($array,JSON_UNESCAPED_UNICODE),FILE_APPEND)){
                return true;
            }
            //770kb的文件内容出来成数组 将创建 16.6MB的文件
            // if(file_put_contents($file, serialize($array),FILE_APPEND)){
            //     return true;
            // }
            // //通过 print_r的方式写入  770kb的文件内容出来成数组 将创建 30MB的文件
            // if(file_put_contents($file, "<?php \r".print_r($array,true).';',FILE_APPEND)){
            //     return true;
            // }
            
        }
        return false;
    }   

    /** 
     * 读取文件中的
     * 
     * @author 林虎
     * @detail each_dir
     * @param file 文件路径
     * @return array 
     */
    public static function get_file_array($file){
        if(empty($file)) return [];
        $handle=fopen($file,'r'); 
        // $cacheArray=unserialize(fread($handle,filesize($file)));
        $cacheArray = json_decode(fread($handle,filesize($file)),true);
        return $cacheArray?:[];
    }

    /** 
     * 生成索引文件
     * 
     * @author 林虎
     * @detail each_dir
     * @return bool 
     */
    public static function each_dir($dir)
    {
        //先判断要遍历的文件是否存在是否为目录
        if(!is_dir($dir))
        {
            return pathinfo($dir)['basename'];
        }
        $files = [];
        //打开文件夹
        if($handle = opendir($dir))
        {
            //读取文件中的内容判断是文件还是文件夹
            while( ($file = readdir($handle))!=false ){
                if($file != '..' && $file != '.'){
                    //继续遍历文件夹下的子文件夹 注意路径
                    $files[$file] = self::each_dir($dir . '/' .$file);
                } else {
                    $files[] = $file;
                }
            }
            closedir($handle);
            unset($files[0]);
            unset($files[1]);
            return $files;
        }
        return [];
    }


    
}